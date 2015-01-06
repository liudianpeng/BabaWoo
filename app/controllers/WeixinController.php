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
		
		$weixin->onMessage(array('event', 'location'), function($message, $user)
		{
			$latlng = BaiduMap::geoConv(array($message->latitude, $message->longitude));
			$nearByStops = Stop::nearBy($latlng[0], $latlng[1])->distanceAscending($latlng[0], $latlng[1])->get();
			
			$reply_text = '';
			
			if(!$user->favorite)
			{
				return;
			}
			
			foreach($nearByStops as $stop)
			{
				foreach($stop->lines as $line)
				{
					if(in_array($line->pivot->id, $user->favorite->line_stop))
					{
						
						$result = Shjtmap::get('car_monitor', 'px', array('lineid'=>$line->line_id, 'direction'=>(bool) $line->direction, 'stopid'=>$line->pivot->stop_no));

						$next_bus = $result->cars->car[0];

						// 查找下一班车时间，返回距离和预估时间
						$reply_text .= $stop->name . ' ' . $line->name . '->' . $line->terminalStop->name .  ' '
								. $next_bus->terminal . '还有' . $next_bus->stopdis . '站，' . ($next_bus->distance > 1000 ? (round($next_bus->distance / 1000, 1) . '千') : $next_bus->distance) . '米，'
								. '约' . floor($next_bus->time / 60) . '分' . $next_bus->time % 60 . '秒' . '进站' . "\n";

						
					}
				}
			}
			
			if($reply_text)
			{
				replyMessage($reply_text);
			}
			
		});
		
		$weixin->onMessage(array('event', 'click'), function($message, $user)
		{
			if($message->meta->EventKey !== 'GET_NEAR_BY_LINES')
			{
				return;
			}
			
			// 查找用户周围车站
			$latlng = BaiduMap::geoConv(array($user->latitude, $user->longitude));
			$nearByStops = Stop::nearBy($latlng[0], $latlng[1])->distanceAscending($latlng[0], $latlng[1])->get();
			
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
			
			$user->session = $session;
			$user->save();
			
			replyMessage($reply_text);
		});
		
		$weixin->onMessage('text', function($message, $user) use($weixin)
		{
			$session = $user->session;
			
			if(!$session)
			{
				replyMessage('尚未接收公交线路列表');
				return;
			}
			
			$index = $message->content - 1;
			
			if(!array_key_exists($index, $session))
			{
				// 回复的是线路名称，查找这些线路的附近站点
				$latlng = BaiduMap::geoConv(array($user->latitude, $user->longitude));
				$stops = Stop::whereHas('lines', function($q) use($message)
				{
					$q->where('name', 'like', $message->content . '%');
					
				})->distanceAscending($latlng[0], $latlng[1])->get();
				
				$reply_text = ''; $line_no = 0;
				
				// 存储发送给用户的序号-线路
				$session = array();
				
				foreach($stops as $stop)
				{
					
					if(strlen($reply_text . '= ' . $stop->name . ' =' . "\n") > 2048){
						break;
					}
					
					$reply_text .= '= ' . $stop->name . ' =' . "\n";
					
					foreach($stop->lines()->where('name', 'like', $message->content . '%')->get() as $line)
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
				
				$user->session = $session;
				$user->save();
				
				replyMessage($reply_text);
				return;
			}
			
			$line_stop_id = $session[$index];
			$line_stop = DB::table('line_stop')->where('id', $line_stop_id)->first();
			$line = Line::find($line_stop->line_id);
			$stop = Stop::find($line_stop->stop_id);
			
			$favorite = $user->favorite;
			!$favorite && $favorite = (object) array('lines'=>array(), 'line_stop'=>array());
			
			!in_array($line->id, $favorite->lines) && array_push($favorite->lines, $line->id);
			!in_array($line_stop->id, $favorite->line_stop) && $favorite->line_stop[] = $line_stop->id;

			$user->favorite = $favorite;
			$user->save();
			
			$result = Shjtmap::get('car_monitor', 'px', array('lineid'=>$line->line_id, 'direction'=>(bool) $line->direction, 'stopid'=>$line_stop->stop_no));
			
			$next_bus = $result->cars->car[0];
			
			// 查找下一班车时间，返回距离和预估时间
			$reply_text = $stop->name . ' ' . $line->name . '->' . $line->terminalStop->name .  ' '
					. $next_bus->terminal . '还有' . $next_bus->stopdis . '站，' . ($next_bus->distance > 1000 ? (round($next_bus->distance / 1000, 1) . '千') : $next_bus->distance) . '米，'
					. '约' . floor($next_bus->time / 60) . '分' . $next_bus->time % 60 . '秒' . '进站';
			
			replyMessage($reply_text);
			
			// 挂起一个任务，在预估时间少于1分钟时给用户发送一条客服消息
		});
		
	}
	
	public function updateMenu()
	{
		$weixin = new Weixin();
		$menu_config = ConfigModel::firstOrCreate(array('key' => 'wx_menu'));
		
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
