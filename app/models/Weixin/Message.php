<?php

class Message extends Eloquent {
	
	protected $fillable = array('name', 'meta', 'type', 'event');
	
	public function user()
	{
		return $this->belongsTo('User');
	}
	
	public function getMetaAttribute($value)
	{
		return json_decode($value);
	}
	
	public function setMetaAttribute($value)
	{
		$this->attributes['meta'] = json_encode($value, JSON_UNESCAPED_UNICODE);
	}
	
	public function getLatitudeAttribute()
	{
		if(property_exists($this->meta, 'Latitude'))
		{
			return $this->meta->Latitude;
		}
	}
	
	public function getLongitudeAttribute()
	{
		if(property_exists($this->meta, 'Longitude'))
		{
			return $this->meta->Longitude;
		}
	}
	
	public function getContentAttribute()
	{
		if(property_exists($this->meta, 'Content'))
		{
			return $this->meta->Content;
		}
	}
	
	public function getFromUserNameAttribute()
	{
		if(property_exists($this->meta, 'FromUserName'))
		{
			return $this->meta->FromUserName;
		}
	}
	
}
