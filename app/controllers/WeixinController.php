<?php

class WeixinController extends BaseController {
	
	var $weixin;
	
	function __construct() {
		$this->weixin = new Weixin();
	}
	
	/*
	 * 微信API响应页面，用来处理来自微信的请求
	 */
	function serve() {
		
		if(Request::get('echostr')){
			return $this->weixin->verify();
		}
		
		$this->weixin->on_message('event', function($message){
			return $this->weixin->reply_message('Hello World!', $message);
		});
	}
	
}
