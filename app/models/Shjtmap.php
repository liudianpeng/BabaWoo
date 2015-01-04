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
		
//		$this->info('Calling: ' . $url);
		
		$xml = file_get_contents($url);
		
		$simpleXmlObject = simplexml_load_string($xml);
		
		$object = static::parseXml($simpleXmlObject, $as_array);
		
//		$this->info('Object parsed as:');
//		var_export($object); echo "\n";
		
		return $object;
	}
	
}
