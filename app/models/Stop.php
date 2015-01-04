<?php

class Stop extends Eloquent {
	
	protected $fillable = array('name', 'latitude', 'longitude', 'stop_id');

	public function lines()
	{
		return $this->belongsToMany('Line')->withPivot('id');
	}
	
	public function originLines()
	{
		return $this->hasMany('Line', 'origin_stop_id');
	}
	
	public function terminalLines()
	{
		return $this->hasMany('Line', 'terminal_stop_id');
	}
	
	public static function getNearBy($latitude_raw, $longitude_raw)
	{
		$url_geoconv = 'http://api.map.baidu.com/geoconv/v1/?';
		$query_args = array(
			'coords'=>$longitude_raw . ',' . $latitude_raw,
			'ak'=>Config::get('baidumap.ak')
		);
		
		$response = file_get_contents($url_geoconv . urldecode(http_build_query($query_args)));
		
		$point = json_decode($response)->result[0];
		
		$latitude = $point->y;
		$longitude = $point->x;
		
		$query = DB::table('stops')
			->whereBetween('latitude', array($latitude - 0.01, $latitude + 0.01))
			->whereBetween('longitude', array($longitude - 0.01, $longitude + 0.01))
			->orderByRaw('POW(`latitude` - ?, 2) + POW(`longitude` - ?, 2) ASC', array($latitude, $longitude));
		
		$stops = $query->get();
		
		foreach($stops as &$stop)
		{
			$stop = Stop::find($stop->id);
		}
		
		return $stops;
		
	}
	
}
