<?php

class BaiduMap {
	
	public static function getPlaces($location_name, $region = '上海市')
	{
		$query_args = array(
			'query'=>$location_name,
			'region'=>$region,
			'output'=>'json',
			'ak'=>Config::get('baidumap.ak')
		);
		
		$url = 'http://api.map.baidu.com/place/v2/search?' . http_build_query($query_args);
		
		$result = file_get_contents($url);
		
		return json_decode($result);
	}

	public static function geoConv($latlng, $input_type = 1, $output_type = 5)
	{
		$url_geoconv = 'http://api.map.baidu.com/geoconv/v1/?';
		$query_args = array(
			'coords'=>$latlng[1] . ',' . $latlng[0],
			'ak'=>Config::get('baidumap.ak'),
			'from'=>$input_type,
			'to'=>$output_type
		);
		
		$response = file_get_contents($url_geoconv . urldecode(http_build_query($query_args)));
		
		$result = json_decode($response);
		
		if($result->status !== 0)
		{
			Log::error('百度坐标转码错误，输入坐标：' . json_encode($latlng) . '，错误信息：' . $response);
		}
		else
		{
			$point = $result->result[0];
			Log::info('百度坐标转码[' . $input_type . '->' . $output_type . ']：' . $latlng[1] . ',' . $latlng[0] . ' -> ' . $point->x . ',' . $point->y);
			return array($point->y, $point->x);
		}
		
		
	}
	
}