<?php

use Weixin\Utils\WXBizMsgCrypt;

class WeixinQY {
	
	private $corp_id;
	private $secret;
	private $agent_id;
	private $token;
	private $encoding_aes_key;
	
	protected $wxcpt;
			
	function __construct() {
		
		// 从配置中获取这些公众账号身份信息
		foreach(array(
			'corp_id',
			'secret',
			'agent_id',
			'token',
			'encoding_aes_key',
		) as $item){
			$this->$item = Config::get('weixin.consultant.' . $item);
		}
		
		$this->wxcpt = new WXBizMsgCrypt($this->token, $this->encoding_aes_key, $this->corp_id);

	}
	
	/*
	 * 验证来源为微信
	 * 放在用于响应微信消息请求的脚本最上端
	 */
	function verify(){
		$sVerifyMsgSig = Request::get('msg_signature');
		$sVerifyTimeStamp = Request::get('timestamp');
		$sVerifyNonce = Request::get('nonce');
		$sVerifyEchoStr = Request::get('echostr');

		// 需要返回的明文
		$sEchoStr = '';
		$errCode = $this->wxcpt->VerifyURL($sVerifyMsgSig, $sVerifyTimeStamp, $sVerifyNonce, $sVerifyEchoStr, $sEchoStr);
		
		if($errCode == 0) {
			return $sEchoStr;
		}else{
			error_log($errCode);
			exit;
		}
	}
	
	function call($url, $data = null, $method = 'GET'){
		
		if(!is_null($data) && $method === 'GET'){
			$method = 'POST';
		}
		
		switch(strtoupper($method)){
			case 'GET':
				$response = file_get_contents($url);
				break;
			
			case 'POST':
				$ch = curl_init($url);

				curl_setopt_array($ch, array(
					CURLOPT_POST => TRUE,
					CURLOPT_RETURNTRANSFER => TRUE,
					CURLOPT_HTTPHEADER => array(
						'Content-Type: application/json'
					),
					CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)
				));

				$response = curl_exec($ch);
				break;
		}
		
		if(!is_null(json_decode($response))){
			$response = json_decode($response);
		}
		
		return $response;
		
	}
	
	/**
	 * 获得站点到微信的access_token
	 * 并缓存于站点数据库
	 * 可以判断过期并重新获取
	 */
	function get_access_token(){
		
		$access_token_config = ConfigModel::firstOrCreate(array('key'=>'wx_access_token'));

		$stored = json_decode($access_token_config->value);
		
		if($stored && $stored->expires_at > time()){
			return $stored->token;
		}
		
		$query_args = array(
			'corpid'=>$this->corp_id,
			'corpsecret'=>$this->secret
		);
		
		$return = $this->call('https://qyapi.weixin.qq.com/cgi-bin/gettoken?' . http_build_query($query_args));
		
		if($return->access_token){
			$access_token_config->value = json_encode(array('token'=>$return->access_token, 'expires_at'=>time() + $return->expires_in - 60));
			$access_token_config->save();
			return $return->access_token;
		}
		
		error_log('Get access token failed. ' . json_encode($return));
		
	}
	
	function create_member(array $data = array()){
		
		$data_format = array(
			'userid'=>'zhangsan',
			'name'=>'张三',
			'department'=>array(1, 2),
			'position'=>'产品经理',
			'mobile'=>'15913215421',
			'gender'=>1,
			'tel'=>'62394',
			'email'=>'zhangsan@gzdev.com',
			'weixinid'=>'zhangsan4dev',
			'extattr'=>array(
				'attrs'=>array(
					array('name'=>'爱好', 'value'=>'旅游'),
					array('name'=>'卡号', 'value'=>'1234567234')
				)
			)
		);
		
		$url = 'https://qyapi.weixin.qq.com/cgi-bin/user/create?access_token=' . $this->get_access_token();
		
		return $this->call($url, array_intersect_key($data, $data_format));
	}
	
	function update_member(array $data = array()){
		
		$data_format = array(
			'userid'=>'zhangsan',
			'name'=>'李四',
			'department'=>array(1),
			'position'=>'后台工程师',
			'mobile'=>'15913215421',
			'gender'=>1,
			'tel'=>'62394',
			'email'=>'zhangsan@gzdev.com',
			'weixinid'=>'lisifordev',
			'enable'=>1,
			'extattr'=>array(
				'attrs'=>array(
					array('name'=>'爱好', 'value'=>'旅游'),
					array('name'=>'卡号', 'value'=>'1234567234')
				)
			)
		);
		
		$url = 'https://qyapi.weixin.qq.com/cgi-bin/user/update?access_token=' . $this->get_access_token();
		
		return $this->call($url, array_intersect_key($data, $data_format));
	}
	
	function remove_member($user_id){
		$url = 'https://qyapi.weixin.qq.com/cgi-bin/user/delete?access_token=' . $this->get_access_token() . '&userid=' . $user_id;
		return $this->call($url);
	}
	
	function get_member($user_id){
		$url = 'https://qyapi.weixin.qq.com/cgi-bin/user/get?access_token=' . $this->get_access_token() . '&userid=' . $user_id;
		return $this->call($url);
	}
	
	function get_department_member($department_id, $fetch_child = true, $status = 0){
		
		$query_args = array(
			'access_token'=>$this->get_access_token(),
			'department_id'=>$department_id,
			'fetch_child'=>(int) $fetch_child,
			'status'=>$status
		);
		
		$url = 'https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?' . http_build_query($query_args);
		
		return $this->call($url);
	}
	
	/**
	 * 生成OAuth授权地址
	 */
	function generate_oauth_url($redirect_uri = null, $state = '', $scope = 'snsapi_base'){
		
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
		
		$query_args = array(
			'appid'=>$this->corp_id,
			'redirect_uri'=>is_null($redirect_uri) ? URL::current() : $redirect_uri,
			'response_type'=>'code',
			'scope'=>$scope,
			'state'=>$state
		);
		
		$url .= http_build_query($query_args) . '#wechat_redirect';
		
		return $url;
		
	}
	
	/**
	 * 生成授权地址并跳转
	 */
	function oauth_redirect($redirect_uri = null, $state = '', $scope = 'snsapi_base'){
		
		if(headers_sent()){
			exit('Could not perform an OAuth redirect, headers already sent');
		}
		
		$url = $this->generate_oauth_url($redirect_uri, $state, $scope);
		
		header('Location: ' . $url);
		exit;
		
	}
	
	/**
	 * OAuth方式获得用户信息
	 * @return on success: {"UserId":"USERID", "DeviceId":"DEVICEID"}, on failure: {"errcode": "40029", "errmsg": "invalid code"}
	 */
	function oauth_get_user_info($code = null){
		
		if(is_null($code) && Request::get('code')){
			$code = Request::get('code');
		}
		
		if(is_null($code)){
			$this->oauth_redirect();
		}
		
		$url = 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?';
		
		$query_vars = array(
			'access_token'=>$this->get_access_token(),
			'code'=>$code,
			'agentid'=>$this->agent_id
		);
		
		$url .= http_build_query($query_vars);
		
		$user_info = $this->call($url);
		
		return $user_info;
	}
	
	function on_message($type, $callback){
		
		if(!isset($GLOBALS["HTTP_RAW_POST_DATA"])){
			return false;
		}
		
		$message = $this->decrypt_message();
		
		// 事件消息			
		if($message->MsgType === $type){
			$callback($message);
		}
		
		return $this;
		
	}
	
	function decrypt_message() {
		
		$sReqMsgSig = Request::get("msg_signature");
		$sReqTimeStamp = Request::get("timestamp");
		$sReqNonce = Request::get("nonce");
		
		// post请求的密文数据
		$sReqData = $this->input->data();
		$sMsg = "";  // 解析之后的明文
		$errCode = $this->wxcpt->DecryptMsg($sReqMsgSig, $sReqTimeStamp, $sReqNonce, $sReqData, $sMsg);
		if($errCode === 0){
			
			$message = json_decode(json_encode(simplexml_load_string($sMsg, null, LIBXML_NOCDATA)));
			
			if($message === null){
				error_log('XML parse error when decrypting message.');
				exit;
			}
			
//			error_log(json_encode($message));
			
			return $message;
			
		}else{
			error_log('Error occured when decrypting message ' . $errCode);
			exit;
		}
	}
	
	function encrypt_message($sRespData) {
		
		simplexml_load_string($sRespData, null, LIBXML_NOCDATA);
		
		$sEncryptMsg = ""; //xml格式的密文
		$errCode = $this->wxcpt->EncryptMsg($sRespData, time(), rand(1E6, 1E7 - 1), $sEncryptMsg);
		
		if($errCode === 0){
			return $sEncryptMsg;
		}else{
			error_log('Error occured when encrypting message: ' . $errCode);
		}
	}
	
	function reply_message($content, $received_message){
		
		$message = array(
			'content'=>$content,
			'from_user'=>$received_message->ToUserName,
			'to_user'=>$received_message->FromUserName
		);
		
		$xml_message = $this->load->view('weixin/message_reply_text', $message, true);
		
		return $this->encrypt_message($xml_message);
		
	}
	
	function reply_news_message($articles, $received_message){
		
		$message = array(
			'count'=>count($articles),
			'articles'=>$articles,
			'from_user'=>$received_message->ToUserName,
			'to_user'=>$received_message->FromUserName
		);
		
		$xml_message = View::make('weixin/message_reply_news', $message, true);
		
		return $this->encrypt_message($xml_message);
	}
	
	function transfer_customer_service($received_message){
		return View::make('weixin/transfer_customer_service', compact('received_message'));
	}
	
	function send_message($to, $message, $type = 'text'){
		$result = $this->call('https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token=' . $this->get_access_token(), array(
			'touser'=>$to,
			'msgtype'=>$type,
			'agentid'=>0,
			'text'=>array(
				'content'=>$message
			)
		));
		
		return $result;
	}
	
}
