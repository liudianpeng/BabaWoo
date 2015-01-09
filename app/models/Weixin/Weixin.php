<?php
/**
 * Weixin library for Laravel
 * @author Uice Lu <uicestone@gmail.com>
 * @version 0.61 (2014/1/9)
 */
class Weixin {
	
	private $account;
	
	private $token; // 微信公众账号后台 / 高级功能 / 开发模式 / 服务器配置
	private $app_id; // 开发模式 / 开发者凭据
	private $app_secret; // 同上
	
	private $message;
	private $user;

	public function __construct($account = 'default')
	{
		$this->account = $account;
		// 从WordPress配置中获取这些公众账号身份信息
		foreach(array(
			'app_id',
			'app_secret',
			'token'
		) as $item)
		{
			$this->$item = Config::get('weixin.' . $account . '.' . $item);
		}
	}
	
	/*
	 * 验证来源为微信
	 * 放在用于响应微信消息请求的脚本最上端
	 */
	public function verify()
	{
		$sign = array(
			$this->token,
			Input::get('timestamp'),
			Input::get('nonce')
		);
		
		sort($sign, SORT_STRING);
		
		if(sha1(implode($sign)) !== Input::get('signature'))
		{
			exit('Signature verification failed.');
		}
		
		if(Input::get('echostr'))
		{
			echo Input::get('echostr');
		}
	}
	
	protected function call($url, $data = null, $method = 'GET', $type = 'form-data')
	{
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
					CURLOPT_POSTFIELDS => $type === 'json' ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data,
					CURLOPT_HTTPHEADER => $type === 'json' ? array(
						'Content-Type: application/json'
					) : array(),
					CURLOPT_SSL_VERIFYHOST => FALSE,
					CURLOPT_SSL_VERIFYPEER => FALSE,
				));
				$response = curl_exec($ch);

				if(!$response){
					exit(curl_error($ch));
				}

				curl_close($ch);
				break;
		}
		if(!is_null(json_decode($response))){
			$response = json_decode($response);
		}

		Log::info('Weixin API called: ' . $url);
		
		return $response;
	}
	
	/**
	 * 获得站点到微信的access token
	 * 并缓存于站点数据库
	 * 可以判断过期并重新获取
	 */
	protected function getAccessToken()
	{
		$access_token_config = ConfigModel::firstOrCreate(array('key'=>'wx_' . ($this->account === 'default' ? '' : $this->account . '_') . 'access_token'));
		$stored = json_decode($access_token_config->value);
		
		if($stored && $stored->expires_at > time())
		{
			return $stored->token;
		}
		
		$query_args = array(
			'grant_type'=>'client_credential',
			'appid'=>$this->app_id,
			'secret'=>$this->app_secret
		);
		
		$return = $this->call('https://api.weixin.qq.com/cgi-bin/token?' . http_build_query($query_args));
		
		if($return->access_token)
		{
			$access_token_config->value = json_encode(array('token'=>$return->access_token, 'expires_at'=>time() + $return->expires_in - 60));
			$access_token_config->save();
			return $return->access_token;
		}
		
		Log::error('Get access token failed. ' . json_encode($return));
		
	}
	
	/**
	 * 直接获得用户信息
	 * 仅在用户与公众账号发生消息交互的时候才可以使用
	 * 换言之仅可用于响应微信消息请求的脚本中
	 */
	public function getUserInfo($openid, $lang = 'zh_CN')
	{
		
		$url = 'https://api.weixin.qq.com/cgi-bin/user/info?';
		
		$query_vars = array(
			'access_token'=>$this->getAccessToken(),
			'openid'=>$openid,
			'lang'=>$lang
		);
		
		$url .= http_build_query($query_vars);
		
		$user_info = $this->call($url);
		
		return $user_info;
		
	}
	
	/**
	 * 生成OAuth授权地址
	 */
	public function generateOAuthUrl($redirect_uri = null, $state = '', $scope = 'snsapi_base')
	{
		
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
		
		$query_args = array(
			'appid'=>$this->app_id,
			'redirect_uri'=>is_null($redirect_uri) ? url() : $redirect_uri,
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
	public function oauthRedirect($redirect_uri = null, $state = '', $scope = 'snsapi_base')
	{
		
		if(headers_sent())
		{
			exit('Could not perform an OAuth redirect, headers already sent');
		}
		
		$url = $this->generateOAuthUrl($redirect_uri, $state, $scope);
		
		header('Location: ' . $url);
		exit;
		
	}
	
	/**
	 * 根据一个OAuth授权请求中的code，获得并存储用户授权信息
	 * 通常不应直接调用此方法，而应调用getOAuthInfo()
	 */
	protected function getOAuthToken($code = null)
	{
		if(is_null($code))
		{
			if(is_null(Input::get('code')))
			{
				header('Location: ' . $this->generateOauthUrl(url(Input::server('REQUEST_URI'))));
				exit;
			}
			
			$code = Input::get('code');
		}
		
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?';
		$query_args = array(
			'appid'=>$this->app_id,
			'secret'=>$this->app_secret,
			'code'=>$code,
			'grant_type'=>'authorization_code'
		);
		$auth_result = $this->call($url . http_build_query($query_args));
		if(!isset($auth_result->openid))
		{
			Log::error('Get OAuth token failed. ' . json_encode($auth_result));
			exit;
		}
		
		$auth_result->expires_at = $auth_result->expires_in + time();
		
		// 客户未关注，但已经储存在数据表中，将其open_id更新进来
		// TODO: CHANGE THIS to a common user create and identify function
		if(Input::get('hash') && $client = Client::where('open_id', Input::get('hash'))->first())
		{
			$client->open_id = $auth_result->openid;
			$client->save();
		}
		
		Session::set('weixin.open_id', $auth_result->openid);
		
		return $auth_result;
	}
	
	/**
	 * 刷新用户OAuth access token
	 * 通常不应直接调用此方法，而应调用getOAuthInfo()
	 */
	protected function refreshOAuthToken($refresh_token)
	{
		
		$url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?';
		
		$query_args = array(
			'appid'=>$this->app_id,
			'grant_type'=>'refresh_token',
			'refresh_token'=>$refresh_token,
		);
		
		$url .= http_build_query($query_args);
		
		$auth_result = $this->call($url);
		
		return $auth_result;
	}
	
	/**
	 * 根据用户请求的access token，获得用户OAuth信息
	 * 所谓OAuth信息，是用户和站点交互的凭据，里面包含了用户的openid，access token等
	 * 并不包含用户的信息，我们需要根据OAuth信息，通过getUserInfoOAuth()去获得
	 * @deprecated 这个方法在需要储存access token的时候才要用到，目前由于有cookie，暂时不需要储存access token
	 * @todo access token应该考虑存储到用户信息中
	 */
	public function getOAuthInfo($access_token = null)
	{
		// 尝试从请求中获得access token
		if(is_null($access_token) && Input::get('access_token'))
		{
			$access_token = Input::get('access_token');
		}
		
		// 如果没能获得access token，我们猜这是一个OAuth授权请求，直接根据code获得OAuth信息
		if (empty($access_token)) {
			return $this->getOAuthToken();
		}
		
		exit('method: getOAuthInfo is deprecated.');
		
		$auth_info = json_decode(get_option('wx_oauth_token_' . $access_token));
		// 从数据库中拿到的access token发现是过期的，那么需要刷新
		if ($auth_info->expires_at <= time()) {
			$auth_info = $this->refreshOAuthToken($auth_info->refresh_token);
		}
		return $auth_info;
	}
	
	/**
	 * OAuth方式获得用户信息
	 * 注意，access token的scope必须包含snsapi_userinfo，才能调用本函数获取
	 */
	public function getUserInfoOAuth($lang = 'zh_CN')
	{
		
		$url = 'https://api.weixin.qq.com/sns/userinfo?';
		
		$auth_info = $this->getOAuthInfo();
		
		$query_vars = array(
			'access_token'=>$auth_info->access_token,
			'openid'=>$auth_info->openid,
			'lang'=>$lang
		);
		
		$url .= http_build_query($query_vars);
		
		$user_info = $this->call($url);
		
		return $user_info;
	}
	
	/**
	 * 生成一个带参数二维码的信息
	 * @param int $scene_id $action_name 为 'QR_LIMIT_SCENE' 时为最大为100000（目前参数只支持1-100000）
	 * @param array $action_info
	 * @param string $action_name 'QR_LIMIT_SCENE' | 'QR_SCENE'
	 * @param int $expires_in
	 * @return array 二维码信息，包括获取的URL和有效期等
	 */
	public function generateQrCode($action_info = array(), $action_name = 'QR_SCENE', $expires_in = '1800')
	{
		// TODO 过期scene应该要回收
		// TODO scene id 到达100000后无法重置
		// TODO QR_LIMIT_SCENE只能有100000个
		$url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $this->getAccessToken();
		
		$last_scene_id_config = ConfigModel::firstOrCreate(array('key'=>'wx_last_qccode_scene_id'));
		!$last_scene_id_config->value && $last_scene_id_config->value = 0;
		$last_scene_id_config->value ++;
		$last_scene_id_config->save();
		
		$scene_id = $last_scene_id_config->value;
		
		if($scene_id > 100000)
		{
			$scene_id = 1; // 强制重置
		}
		
		$action_info['scene']['scene_id'] = $scene_id;
		
		$post_data = array(
			'expire_seconds'=>$expires_in,
			'action_name'=>$action_name,
			'action_info'=>$action_info,
		);
		
		$ch = curl_init($url);
		
		curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
			CURLOPT_POSTFIELDS => json_encode($post_data)
		));
		
		$response = json_decode(curl_exec($ch));
		
		if(!property_exists($response, 'ticket'))
		{
			return $response;
		}
		
		$qrcode = array(
			'url'=>'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($response->ticket),
			'expires_at'=>time() + $response->expire_seconds,
			'action_info'=>$action_info,
			'ticket'=>$response->ticket
		);
		
		ConfigModel::create(array('key'=>'wx_qrscene_' . $scene_id, 'value'=>json_encode($qrcode)));
		
		return $qrcode;
		
	}
	
	/**
	 * 删除微信公众号会话界面菜单
	 */
	public function removeMenu()
	{
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $this->getAccessToken();
		return $this->call($url);
	}
	
	/**
	 * 创建微信公众号会话界面菜单
	 */
	public function createMenu($data)
	{
		
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $this->getAccessToken();
		
		$ch = curl_init($url);
		
		curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json'
			),
			CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)
		));
		
		$response = json_decode(curl_exec($ch));
		
		return $response;
		
	}
	
	/**
	 * 获得微信公众号会话界面菜单
	 */
	function getMenu()
	{
		$menu = $this->call('https://api.weixin.qq.com/cgi-bin/menu/get?access_token=' . $this->getAccessToken());
		return $menu;
	}
	
	/**
	 * @param string|array $type MsgType, or array(MsgType, Event)
	 * @param Closure $callback
	 */
	function onMessage($type, $callback)
	{
		global $message_raw;
		
		$message_raw = (object) (array) simplexml_load_string(Request::getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
		
		if(!property_exists($message_raw, 'FromUserName'))
		{
			return;
		}
		
		if(is_null($this->user))
		{
			$this->user = User::firstOrCreate(array('openid'=>$message_raw->FromUserName));
		}
		
		if(is_null($this->message))
		{

			if(!$this->user->name)
			{
				$user_info = $this->getUserInfo($message_raw->FromUserName);

				$this->user->fill(array(
					'name'=>$user_info->nickname,
					'meta'=>$user_info
				));

			}
			
			Log::info('收到微信消息：' . json_encode($message_raw));

			$this->user->last_active_at = date('Y-m-d H:i:s', $message_raw->CreateTime);

			if(property_exists($message_raw, 'Event') && $message_raw->Event === 'LOCATION')
			{
				$this->user->latitude = $message_raw->Latitude;
				$this->user->longitude = $message_raw->Longitude;
				$this->user->precision = $message_raw->Precision;
			}

//			if($message_raw->MsgType === 'location')
//			{
//				$this->user->latitude = $message_raw->Location_X;
//				$this->user->longitude = $message_raw->Location_Y;
//			}

			$this->user->save();
			
			$this->message = new Message();

			$this->message->fill(array(
				'type'=>$message_raw->MsgType,
				'event'=>property_exists($message_raw, 'Event') ? $message_raw->Event : '',
				'meta'=>$message_raw
			));

			$this->message->user()->associate($this->user);

			try
			{
				$this->message->save();
			}
			catch(PDOException $e)
			{
				// A "Dupulicate Entry" exception is considered normal here
				if(strpos($e->getMessage(), 'Duplicate entry') === false)
				{
					throw new PDOException($e->getMessage(), $e->getCode(), $e);
				}
			}
			
		}
		
		if(is_string($type) && $type !== $message_raw->MsgType)
		{
			return;
		}
		
		if(is_array($type) && !($type[0] === $message_raw->MsgType && property_exists($message_raw, 'Event') && strtolower($type[1]) === strtolower($message_raw->Event)))
		{
			return;
		}
		
		global $user; $user = $this->user;
		
		function replyMessage($content)
		{
			if(!$content)
			{
				return;
			}
			
			global $message_raw, $user;
			
			$received_message =  $message_raw;
			echo View::make('weixin/message-reply-text', compact('content', 'received_message'));
			
			Log::info('向用户' . $user->name . '发送了消息: ' . $content);
		}
		
		/**
		 * @todo not adapt to Laravel yet
		 */
		function replyPostMessage($reply_posts)
		{
			!is_array($reply_posts) && $reply_posts = array($reply_posts);
			$reply_posts_count = count($reply_posts);
			return View::make('weixin/message-reply-news', compact('reply_posts_count'));
		}

		function transferCustomerService($received_message)
		{
			return View::make('weixin/transfer-customer-service', array('fromUser'=>$received_message->fromUserName));
		}
		
		$callback($this->message, $this->user);
		
		return $this;
		
	}
	
	public function sendServiceMessage($to_user, $contents, $type = 'text')
	{
		if($to_user->last_active_at->timestamp < time() - 60 * 48){
			Log::error($to_user->name . ' 已超过48小时未活动，客服消息发送失败');
			return;
		}
		
		$data = array('touser'=>$to_user->openid, 'msgtype'=>$type);
		
		if($type === 'text')
		{
			$data['text']['content'] = $contents;
		}
		
		$this->call('https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $this->getAccessToken(), $data, 'POST', 'json');
	}
	
}
