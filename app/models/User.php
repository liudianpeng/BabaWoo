<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;
use Illuminate\Auth\Reminders\RemindableTrait;
use Illuminate\Auth\Reminders\RemindableInterface;

class User extends Eloquent implements UserInterface, RemindableInterface {

	use UserTrait, RemindableTrait;

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'users';

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = array('password', 'remember_token');
	
	/**
	 * 这里的latitude和longitude来自微信消息，为设备数据，未转码到百度地图坐标
	 * @var array
	 */
	protected $fillable = array('name', 'openid', 'latitute', 'longitude', 'precision', 'meta', 'session', 'favorite');

	public function messages()
	{
		return $this->hasMany('Message');
	}
	
	public function getMetaAttribute($value)
	{
		return json_decode($value);
	}
	
	public function setMetaAttribute($value)
	{
		$this->attributes['meta'] = json_encode($value);
	}
	
	public function getSessionAttribute($value)
	{
		return json_decode($value);
	}
	
	public function setSessionAttribute($value)
	{
		$this->attributes['session'] = json_encode($value);
	}
	
	public function getFavoriteAttribute($value)
	{
		return json_decode($value);
	}
	
	public function setFavoriteAttribute($value)
	{
		$this->attributes['favorite'] = json_encode($value);
	}
	
	public function getDates()
	{
		return array('last_active_at');
	}
	
}
