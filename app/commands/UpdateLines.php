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
	
	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		
		$lines = Shjtmap::get('line', $this->option('region'), array(), false, true)['line'];
		
		foreach($lines as $line)
		{
			
			try{
			
				$line_base = $line['@attributes'];

				$query_args = $this->option('region') === 'px' ? array('name'=>$line_base['actual']) : array('linename'=>$line_base['name']);

				if(Line::where('name', $line_base['name'])->get()->count() === 2)
				{
					continue;
				}
				
				$line_detail = Shjtmap::get('get_line_info_by_name', $this->option('region'), $query_args);

				if($this->option('region') === 'pd')
				{
					$line_detail = $line_detail->linedetail;
				}

				$directions = (array) Shjtmap::get('get_line', $this->option('region'), array('lineid'=>$line_detail->line_id));

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
						'region'=>$this->option('region'),
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

					Log::info($line['name'] . ' saved.');
					$this->info($line['name'] . ' saved.');
				}
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
			array('region', 'r', InputOption::VALUE_REQUIRED, 'Bus line region. Available values are pd, px'),
		);
	}

}
