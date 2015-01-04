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
		
		$weixin->onMessage('event', function($message) use($weixin){
			if($message->Event === 'LOCATION')
			{
				// 查找用户周围车站
				$nearByStops = Stop::getNearBy($message->Latitude, $message->Longitude);
				
				$reply_text = ''; $line_no = 1;
				
				foreach($nearByStops as $stop)
				{
					
					if(strlen($reply_text . '= ' . $stop->name . ' =' . "\n") > 2028){
						break;
					}
					
					$reply_text .= '= ' . $stop->name . ' =' . "\n";
					
					foreach($stop->lines as $line)
					{
						$item = ($line_no) . '. ' . $line->name . '->' . $line->terminalStop->name;
						$line_no ++;
						
						if(strlen($reply_text . $item . "\n") > 2048)
						{
							break;
						}
						
						$reply_text .= $item . "\n";
					}
				}
				
				// 按站分组
				echo $weixin->replyMessage($reply_text, $message);
		
				// 搜索收藏的 公交线路-站 并给出结果
				// 返回所有 公交线路-站 并挂起序号等待回复
			}
			
		});
		
		$weixin->onMessage('text', function($message) use($weixin){
			// 查找该用户挂起的序号，映射到 公交线路-站
			// 将本 公交线路 - 站加入收藏
			// 查找下一班车时间，返回距离和预估时间
			// 挂起一个任务，在预估时间少于1分钟时给用户发送一条客服消息
			echo $weixin->replyMessage('Hello World', $message);
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
