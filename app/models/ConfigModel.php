<?php

class ConfigModel extends Eloquent {
	
	protected $table = 'config';
	protected $fillable = array('key', 'value');
	
}