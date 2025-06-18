<?php

/*
 * This file is part of horaja/flarum-ext-ai-tagger.
 *
 * Author: Husain Raja
 * Email: horaja@cs.cmu.edu
 */

namespace Horaja\AiTagger\Console;

use Flarum\Console\AbstractCommand;
use Illuminate\Contracts\Queue\Queue;

class WorkQueueCommand extends AbstractCommand
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('ai:work-queue')
			->setDescription('Run the AI tagger queue worker.');
	}

	function fire()
	{
		$queue = resolve(Queue::class);

		$this->info('Starting AI tagger queue worker...');

		while (true)
		{
			$job = $queue->pop('default');

			if ($job)
			{
				$this->info('Processing job: ' . $job->resolveName());
				$job->fire();
				$this->info('Finished job: ' . $job->resolveName());
			} else
			{
				sleep(5);
			}
		}
	}
}