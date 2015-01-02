<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateLines extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'update:lines';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update bus lines in Shanghai.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}
	
	protected function parseXml(SimpleXMLElement $xml, $as_array = false)
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
	
	protected function getFromApi($api_name, $region = 'px', $query_args = array(), $use_key = true, $as_array = false)
	{
		if($use_key)
		{
			$query_args = array_merge($query_args, array(
				'my'=>strtoupper(md5(Config::get('shjtmap.' . $region . '.key') . date('Y-m-dH:i'))),
				't'=>date('Y-m-dH:i')
			));
		}
		
		$url = Config::get('shjtmap.' . $region . '.' . $api_name) . '?' . http_build_query($query_args);
		
		$this->info('Calling: ' . $url);
		
		$xml = simplexml_load_file($url);
		
		$object = $this->parseXml($xml, $as_array);
		
		$this->info('Object parsed as:');
		
		var_export($object); echo "\n";
		
		return $object;
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		
		$lines = $this->getFromApi('line', $this->option('region'), array(), false, true)['line'];
		
		foreach($lines as $line)
		{
			$line_base = $line['@attributes'];
			
			$query_args = $this->option('region') === 'px' ? array('name'=>$line_base['actual']) : array('linename'=>$line_base['name']);
			
			$line_detail = $this->getFromApi('get_line_info_by_name', $this->option('region'), $query_args);
			
			if($this->option('region') === 'pd')
			{
				$line_detail = $line_detail->linedetail;
			}
			
			$directions = (array) $this->getFromApi('get_line', $this->option('region'), array('lineid'=>$line_detail->line_id));
			
			foreach($directions as $direction)
			{
				$direction->direction = $direction->direction === 'true';
				
				$line = Line::firstOrCreate(array(
					'slug'=>$line_detail->line_name,
					'direction'=>$direction->direction
				));
				
				$origin_stop = Stop::firstOrCreate(array('name'=>$line_detail->start_stop));
				$terminal_stop = Stop::firstOrCreate(array('name'=>$line_detail->end_stop));

				$line->fill(array(
					'name'=>$line_base['name'],
					'line_id'=>$line_detail->line_id,
					'first_vehicle_hour'=>$direction->direction ? $line_detail->start_earlytime : $line_detail->end_earlytime,
					'last_vehicle_hour'=>$direction->direction ? $line_detail->start_latetime : $line_detail->end_latetime
				));
				
				$line->originStop()->associate($direction->direction ? $origin_stop : $terminal_stop);
				$line->terminalStop()->associate($direction->direction ? $terminal_stop : $origin_stop);

				$line->save();
				
				if(!property_exists($direction, 'stop'))
				{
					continue;
				}
				
				foreach($direction->stop as $stop_raw)
				{
					$stop = Stop::firstOrCreate(array(
						'name'=>$stop_raw->zdmc,
					));
					
					try
					{
						$line->stops()->attach($stop->id, array('stop_no'=>$stop_raw->id));
					}
					catch(PDOException $e)
					{
						// A "Dupulicate Entry" exception is considered normal here
						if(strpos($e->getMessage(), 'Duplicate entry') === false)
						{
							throw new PDOException($e->getMessage(), $e->getCode(), $e);
						}
					}
				}
			}
			
			Log::info($line['name'] . ' saved.');
			$this->info($line['name'] . ' saved.');
			sleep(rand(0, 2));
			
		}
		
	}
	
	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
//			array('example', InputArgument::REQUIRED, 'An example argument.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('region', 'r', InputOption::VALUE_REQUIRED, 'Bus line region. Available values are pd, px'),
		);
	}

}
