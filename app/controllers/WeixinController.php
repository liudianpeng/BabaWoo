<?php

class WeixinController extends BaseController {
	
	/*
	 * 微信API响应页面，用来处理来自微信的请求
	 */
	public function serve()
	{
		$weixin = new Weixin();
		
		if(Request::get('echostr')){
			return $weixin->verify();
		}
		
		$weixin->onMessage('event', function($message, $user) use($weixin)
		{
			if($message->Event === 'LOCATION')
			{	
				$favorite = json_decode($user->favorite);
				
				// 查找用户周围车站
				$nearByStops = Stop::getNearBy($message->Latitude, $message->Longitude);
				
				$reply_text = ''; $line_no = 0;
				
				// 存储发送给用户的序号-线路
				$session = array();
				
				foreach($nearByStops as $stop)
				{
					
					if(strlen($reply_text . '= ' . $stop->name . ' =' . "\n") > 2048){
						break;
					}
					
					$reply_text .= '= ' . $stop->name . ' =' . "\n";
					
					foreach($stop->lines as $line)
					{
						$item = ($line_no + 1) . '. ' . $line->name . '->' . $line->terminalStop->name;
						
						if(strlen($reply_text . $item . "\n") > 2048)
						{
							break;
						}
						
						$session[$line_no] = $line->pivot->id;
						$reply_text .= $item . "\n";
						$line_no ++;
					}
				}
				
				$user->session = json_encode($session);
				$user->save();
				
				echo $weixin->replyMessage($reply_text, $message);
			}
			
		});
		
		$weixin->onMessage('text', function($message, $user) use($weixin)
		{
			$session = json_decode($user->session);
			
			if(!$session)
			{
				echo $weixin->replyMessage('尚未接收公交线路列表', $message);
				return;
			}
			
			$index = $message->Content - 1;
			
			if(!array_key_exists($index, $session))
			{
				echo $weixin->replyMessage('序号回复错误', $message);
				return;
			}
			
			$line_stop_id = $session[$index];
			$line_stop = DB::table('line_stop')->where('id', $line_stop_id)->first();
			$line = Line::find($line_stop->line_id);
			$stop = Stop::find($line_stop->stop_id);
			
			$favorite = json_decode($user->favorite);
			!$favorite && $favorite = (object) array('lines'=>array(), 'line_stop'=>array());
			
			!in_array($line->id, $favorite->lines) && $favorite->lines[] = $line->id;
			!in_array($line_stop->id, $favorite->line_stop) && $favorite->line_stop[] = $line_stop->id;
			
			$user->favorite = json_encode($favorite);
			$user->save();
			
			$result = Shjtmap::get('car_monitor', 'px', array('lineid'=>$line->line_id, 'direction'=>(bool) $line->direction, 'stopid'=>$line_stop->stop_no));
			Log::info(json_encode($result->cars->car[0]));
			$next_bus = $result->cars->car[0];
			
			// 查找下一班车时间，返回距离和预估时间
			// 挂起一个任务，在预估时间少于1分钟时给用户发送一条客服消息
			echo $weixin->replyMessage($line->name . '->' . $line->terminalStop->name .  ' ' . $next_bus->terminal . '还有' . $next_bus->stopdis . '站，' . $next_bus->distance . '米，约' . $next_bus->time . '秒后进站', $message);
		});
		
	}
	
	public function updateMenu()
	{
		$weixin = new Weixin();
		$menu_config = ConfigModel::firstOrCreate(array('key' => 'wx_client_menu'));
		
		if(!$menu_config->value){
			$menu = $weixin->getMenu();
			$menu_config->value = json_encode($menu->menu, JSON_UNESCAPED_UNICODE);
			$menu_config->save();
			return $menu_config->value;
		}
		
		$menu = json_decode($menu_config->value);
		$weixin->removeMenu();
		$result = $weixin->createMenu($menu);
		return json_encode($result) . "\n" . json_encode($weixin->getMenu(), JSON_UNESCAPED_UNICODE);
	}
	
	public function removeMenu()
	{
		$weixin = new Weixin();
		return json_encode($weixin->removeMenu()) . "\n" . json_encode($weixin->getMenu());
	}
	
}
