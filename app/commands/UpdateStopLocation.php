<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateStopLocation extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'update:stop-location';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update bus stop locations using Baidu Geocoding API.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$stops = Stop::where('latitude', null)->orWhere('longitude', null)->get();
		foreach($stops as $stop)
		{
			try{
				$result = BaiduMap::getPlaces($stop->name . '-公交车站')->results;
				
				if(!$result)
				{
					$result = BaiduMap::getPlaces($stop->name)->results;
					
					if($result && property_exists($result[0], 'address'))
					{
						$this->info('缩小查找范围: ' . $result[0]->address);
						$result = BaiduMap::getPlaces($stop->name . '-公交车站', '上海市' . $result[0]->address)->results;
					}
				}
				
				if(!$result)
				{
					$this->error($stop->name . '站 没有找到');
					continue;
				}

				$place = $result[0];

				$stop->latitude = $place->location->lat;
				$stop->longitude = $place->location->lng;

				$stop->save();

				$this->info($stop->name . '站 地址已保存');
			}
			catch(Exception $e){
				$this->error($e->getMessage());
				Log::error($e->getMessage());
			}
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
//			array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);
	}

}
