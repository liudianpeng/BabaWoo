<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateStopTransfer extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'update:stop-transfer';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update transferability between stops.';

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
		// 对于每个站点，找出半径500M方块中的所有站点
		
		foreach(Stop::all() as $stop1)
		{
			foreach(Stop::nearBy($stop1->latitude, $stop1->longitude, 100)->get() as $stop2)
			{
				try{
					if($stop1->id === $stop2->id)
					{
						continue;
					}

					$route = BaiduMap::routeMatrix($stop1->latitude . ',' . $stop1->longitude, $stop2->latitude . ',' . $stop2->longitude);

					// 请求百度，获得步行距离，若小于500M，则保存直线距离，步行距离，和步行时间
					if($route[0]->distance->value > 500)
					{
						$this->comment($stop1->name . ' 和 ' . $stop2->name . ' 步行路程' . $route[0]->distance->value . '米，跳过');
						continue;
					}

					$this->info($stop1->name . ' 和 ' . $stop2->name . ' 步行路程' . $route[0]->distance->value . '米，保存');
					$stop1->transferableStops()->attach($stop2->id, array('walking_distance'=>$route[0]->distance->value, 'walking_duration'=>$route[0]->duration->value));
				}
				catch(ErrorException $e)
				{
					$this->error($e->getMessage());
				}
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
