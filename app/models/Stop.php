<?php

class Stop extends Eloquent {
	
	protected $fillable = array('name', 'latitude', 'longitude', 'stop_id');

	public function lines()
	{
		return $this->belongsToMany('Line');
	}
	
	public function originLines()
	{
		return $this->hasMany('Line', 'origin_stop_id');
	}
	
	public function terminalLines()
	{
		return $this->hasMany('Line', 'terminal_stop_id');
	}
	
}
