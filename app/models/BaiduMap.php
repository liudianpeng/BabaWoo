<?php

class BaiduMap {
	
	public static function geoConv($latlng)
	{
		$url_geoconv = 'http://api.map.baidu.com/geoconv/v1/?';
		$query_args = array(
			'coords'=>$latlng[1] . ',' . $latlng[0],
			'ak'=>Config::get('baidumap.ak')
		);
		
		$response = file_get_contents($url_geoconv . urldecode(http_build_query($query_args)));

		$point = json_decode($response)->result[0];
		
		return array($point->y, $point->x);
		
	}
	
}