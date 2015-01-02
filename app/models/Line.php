<?php

class Line extends Eloquent {
	
	protected $fillable = array('name', 'first_vehicle_hour', 'last_vehicle_hour', 'slug', 'line_id', 'direction');

	public function stops()
	{
		return $this->belongsToMany('Stop');
	}
	
	public function originStop()
	{
		return $this->belongsTo('Stop');
	}
	
	public function terminalStop()
	{
		return $this->belongsTo('Stop');
	}
	
}
