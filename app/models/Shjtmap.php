<?php

class Shjtmap {
	
	protected static function parseXml(SimpleXMLElement $xml, $as_array = false)
	{
		$object = json_decode(json_encode($xml), $as_array);
		foreach($as_array ? $object : get_object_vars($object) as $key => $value)
		{
			if(is_string($value))
			{
				$object->$key = trim($value);
			}
		}
		return $object;
	}
	
	public static function get($api_name, $region = 'px', $query_args = array(), $use_key = true, $as_array = false)
	{
		if($use_key)
		{
			$query_args = array_merge($query_args, array(
				'my'=>strtoupper(md5(Config::get('shjtmap.' . $region . '.key') . date('Y-m-dH:i'))),
				't'=>date('Y-m-dH:i')
			));
		}
		
		$url = Config::get('shjtmap.' . $region . '.' . $api_name) . '?' . http_build_query($query_args);
		
//		Log::info('Calling: ' . $url);
		
		$xml = file_get_contents($url);
		
//		Log::info('Got XML: ' . var_export($xml, true));
		
		$simpleXmlObject = simplexml_load_string($xml);
		
		$object = static::parseXml($simpleXmlObject, $as_array);
		
//		Log::info('Object parsed as: ' . var_export($object, true));
		
		return $object;
	}
	
	public static function vehicleMonitor($line, $stop)
	{
		if(property_exists($line, 'pivot'))
		{
			$line_stop = $line->pivot;
		}
		else
		{
			$line_stop = DB::table('line_stop')->where('line_id', $line->id)->where('stop_id', $stop->id)->first();
		}
		
		$result = Shjtmap::get('car_monitor', $line->region, array('lineid'=>$line->line_id, 'direction'=>(bool) $line->direction, 'stopid'=>$line_stop->stop_no));
		
//		Log::info('车辆位置信息：' . var_export($result, true));
		
		try{
			$next_bus = $result->cars->car[0];
			
			$message = $stop->name . ' ' . $line->name . '->' . $line->terminalStop->name .  ' ';
			
			if($next_bus->terminal === 'null')
			{
				$message .= '尚未发车';
			}
			else
			{
				$message .= $next_bus->terminal . '还有' . $next_bus->stopdis . '站，' . ($next_bus->distance > 1000 ? (round($next_bus->distance / 1000, 1) . '千') : $next_bus->distance) . '米，'
					. '约' . floor($next_bus->time / 60) . '分' . $next_bus->time % 60 . '秒' . '进站';
			}
			
			return $message;
			
		}
		catch(ErrorException $e)
		{
			Log::error('未找到车辆位置 ' . $e->getMessage());
		}
	}
	
}
