<?php

class Message extends Eloquent {
	
	protected $fillable = array('name', 'meta', 'type', 'event');
	
	public function user()
	{
		return $this->belongsTo('User');
	}
	
}
