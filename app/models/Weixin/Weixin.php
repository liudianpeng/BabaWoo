<?php
/**
 * Weixin library for Laravel
 * @author Uice Lu <uicestone@gmail.com>
 * @version 0.5 (2014/1/2)
 */
class Weixin {
	
	private $token; // 微信公众账号后台 / 高级功能 / 开发模式 / 服务器配置
	private $app_id; // 开发模式 / 开发者凭据
	private $app_secret; // 同上
	
	private $message_saved;
	
	public function __construct()
	{
		// 从WordPress配置中获取这些公众账号身份信息
		foreach(array(
			'app_id',
			'app_secret',
			'token'
		) as $item)
		{
			$this->$item = Config::get('weixin.default.' . $item);
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
			$_GET['timestamp'],
			$_GET['nonce']
		);
		
		sort($sign, SORT_STRING);
		
		if(sha1(implode($sign)) !== $_GET['signature'])
		{
			exit('Signature verification failed.');
		}
		
		if(isset($_GET['echostr']))
		{
			echo $_GET['echostr'];
		}
	}
	
	protected function call($url)
	{
		Log::info('Weixin API called: ' . $url);
		return file_get_contents($url);
	}
	
	/**
	 * 获得站点到微信的access token
	 * 并缓存于站点数据库
	 * 可以判断过期并重新获取
	 */
	protected function getAccessToken()
	{
		$access_token_config = ConfigModel::firstOrCreate(array('key'=>'wx_client_access_token'));
		
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
		
		$return = json_decode($this->call('https://api.weixin.qq.com/cgi-bin/token?' . http_build_query($query_args)));
		
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
		
		$user_info = json_decode($this->call($url));
		
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
			'redirect_uri'=>is_null($redirect_uri) ? site_url() : $redirect_uri,
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
		
		if($auth_result = json_decode())
		{
			if($auth_result->expires_at >= time())
			{
				return $auth_result->access_token;
			}
		}
		
		if(is_null($code))
		{
			if(empty($_GET['code']))
			{
				header('Location: ' . $this->generateOauthUrl(site_url() . $_SERVER['REQUEST_URI']));
				exit;
			}
			$code = $_GET['code'];
		}
		
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?';
		$query_args = array(
			'appid'=>$this->app_id,
			'secret'=>$this->app_secret,
			'code'=>$code,
			'grant_type'=>'authorization_code'
		);
		$auth_result = json_decode($this->call($url . http_build_query($query_args)));
		if(!isset($auth_result->openid))
		{
			Log::error('Get OAuth token failed. ' . json_encode($auth_result));
			exit;
		}
		
		$auth_result->expires_at = $auth_result->expires_in + time();
		
		if(is_user_logged_in())
		{
			update_user_meta(get_current_user_id(), 'oauth_info', json_encode($auth_result));
		}
		else{
			update_option('wx_oauth_token_' . $auth_result->access_token, json_encode($auth_result));
		}
		
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
		
		$auth_result = json_decode($this->call($url));
		
		return $auth_result;
	}
	
	/**
	 * 根据用户请求的access token，获得用户OAuth信息
	 * 所谓OAuth信息，是用户和站点交互的凭据，里面包含了用户的openid，access token等
	 * 并不包含用户的信息，我们需要根据OAuth信息，通过getUserInfoOAuth()去获得
	 */
	public function getOAuthInfo($access_token = null)
	{
		// 尝试从请求中获得access token
		if(is_null($access_token) && isset($_GET['access_token']))
		{
			$access_token = $_GET['access_token'];
		}
		
		// 如果没能获得access token，我们猜这是一个OAuth授权请求，直接根据code获得OAuth信息
		if (empty($access_token)) {
			return $this->getOAuthToken();
		}
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
		
		$user_info = json_decode($this->call($url));
		
		return $user_info;
	}
	
	/**
	 * 生成支付接口参数，供前端调用
	 * @param string $notify_url 支付结果通知url
	 * @param string $order_id 订单号，必须唯一
	 * @param int $total_price 总价，单位为分
	 * @param string $order_name 订单名称
	 * @param string $attach 附加信息，将在支付结果通知时原样返回
	 * @return array
	 */
	public function generateJsPayArgs($notify_url, $order_id, $total_price, $order_name, $attach = ' ')
	{
		
		$package_data = array(
			'bank_type'=>'WX',
			'body'=>$order_name,
			'attach'=>$attach,
			'partner'=>$this->partner_id,
			'out_trade_no'=>$order_id,
			'total_fee'=>(string)(int) ($total_price * 100),
			'fee_type'=>'1',
			'notify_url'=>$notify_url,
			'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],
			'input_charset'=>'UTF-8'
		);
		ksort($package_data, SORT_STRING);
		$string1 = urldecode(http_build_query($package_data));
		$stringSignTemp = $string1 . '&key=' . $this->partner_key;
		$signValue = strtoupper(md5($stringSignTemp));
		$string2 = http_build_query($package_data, null, null, PHP_QUERY_RFC3986);
		$package = $string1 . '&sign=' . $signValue;
		$nonce_str = (string) rand(1E15, 1E16-1);
		$timestamp = time();
		$pay_sign_data = array(
			'appid'=>get_option('wx_app_id'),
			'timestamp'=>$timestamp,
			'noncestr'=>$nonce_str,
			'package'=>$package,
			'appkey'=>$this->pay_sign_key
		);
		ksort($pay_sign_data, SORT_STRING);
		$string1 = urldecode(http_build_query($pay_sign_data));
		$pay_sign = sha1($string1);
		$pay_request_args = array(
			'appId'=>(string) get_option('wx_app_id'),
			'timeStamp'=>(string) $timestamp,
			'nonceStr'=>(string) $nonce_str,
			'package'=>$package,
			'signType'=>'SHA1',
			'paySign'=>$pay_sign,
		);
		
		return $pay_request_args;
	}
	
	/**
	 * 生成微信收货地址共享接口参数，供前端调用
	 * @return array
	 */
	public function generateJsEditAddressArgs()
	{
		
		$args = array(
			'appId'=>(string) $this->app_id,
			'scope'=>'jsapi_address',
			'signType'=>'sha1',
			'addrSign'=>'',
			'timeStamp'=>(string) time(),
			'nonceStr'=>(string) rand(1E15, 1E16-1)
		);
		
		$sign_args = array(
			'appid'=>$this->app_id,
			'url'=>"http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
			'timestamp'=>$args['timeStamp'],
			'noncestr'=>$args['nonceStr'],
			'accesstoken'=>$this->getOAuthToken($_GET['code'])->access_token
		);
		ksort($sign_args, SORT_STRING);
		$string1 = urldecode(http_build_query($sign_args));
		
		$args['addrSign'] = sha1($string1);
		return $args;
		
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
		
		$scene_id = get_option('wx_last_qccode_scene_id', 0) + 1;
		
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
		
		update_option('wx_qrscene_' . $scene_id, json_encode($qrcode));
		update_option('wx_last_qccode_scene_id', $scene_id);
		
		return $qrcode;
		
	}
	
	/**
	 * 删除微信公众号会话界面菜单
	 */
	public function removeMenu()
	{
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=' . $this->getAccessToken();
		return json_decode($this->call($url));
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
		$menu = json_decode($this->call('https://api.weixin.qq.com/cgi-bin/menu/get?access_token=' . $this->getAccessToken()));
		return $menu;
	}
	
	function onMessage($type, $callback)
	{
		$message_raw = (object) (array) simplexml_load_string(Request::getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
		
		if(!$message_raw)
		{
			Log::error('Weixin message XML parse error. Content: ' . Request::getContent());
			exit;
		}
		
		if($message_raw->MsgType === $type)
		{
			$callback($message_raw);
		}
		
		if(!$this->message_saved)
		{
			$user = User::firstOrCreate(array('openid'=>$message_raw->FromUserName));

			if(!$user->name)
			{
				$user_info = $this->getUserInfo($message_raw->FromUserName);

				$user->fill(array(
					'name'=>$user_info->nickname,
					'meta'=>json_encode($user_info, JSON_UNESCAPED_UNICODE)
				));

			}

			$user->last_active_at = date('Y-m-d H:i:s', $message_raw->CreateTime);

			if($message_raw->Event === 'LOCATION'){
				$user->latitude = $message_raw->Latitude;
				$user->longitude = $message_raw->Longitude;
				$user->precision = $message_raw->Precision;
			}

			$user->save();

			$message = new Message();

			$message->fill(array(
				'type'=>$message_raw->MsgType,
				'event'=>$message_raw->Event,
				'meta'=>json_encode($message_raw, JSON_UNESCAPED_UNICODE)
			));

			$message->user()->associate($user);

			try
			{
				$message->save();
			}
			catch(PDOException $e)
			{
				// A "Dupulicate Entry" exception is considered normal here
				if(strpos($e->getMessage(), 'Duplicate entry') === false)
				{
					throw new PDOException($e->getMessage(), $e->getCode(), $e);
				}
			}
			
			$this->message_saved = true;
			
		}
		
		return $this;
		
	}
	
	function replyMessage($content, $received_message)
	{
		return View::make('weixin/message-reply-text', compact('content', 'received_message'));
	}
	
	function replyPostMessage($reply_posts, $received_message)
	{
		!is_array($reply_posts) && $reply_posts = array($reply_posts);
		$reply_posts_count = count($reply_posts);
		return View::make('weixin/message-reply-news', compact('reply_posts_count'));
	}
	
	function transferCustomerService($received_message)
	{
		return View::make('weixin/transfer-customer-service', array('fromUser'=>$received_message->fromUserName));
	}
	
}