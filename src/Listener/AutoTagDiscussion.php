<?php

/*
 * This file is part of horaja/flarum-ext-ai-tagger.
 *
 * Author: Husain Raja
 * Email: horaja@cs.cmu.edu
 */

namespace Horaja\AiTagger\Listener;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Horaja\AiTagger\Job\GenerateTagsJob;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

class AutoTagDiscussion
{
	/**
	 * @var Dispatcher
	 */
	protected $bus;

	/**
	 * @var LoggerInterface
	 */
	protected $log;

	/**
	 * @param Dispatcher $bus The job bus dispatcher.
	 * @param LoggerInterface $log The Flarum logger.
	 */
	public function __construct(Dispatcher $bus, LoggerInterface $log)
	{
		$this->bus = $bus;
		$this->log = $log;
	}

	/**
	 * Handle the event when a discussion is being saved.
	 * Listens for new discussions and dispatches a background job to generate
	 * and apply AI-suggested tags.
	 * 
	 * @param Saving $event The Flarum event object.
	 * @return void
	 */
	public function handle(Saving $event)
	{		
		$actor = $event->actor;
		$discussion = $event->discussion;

		if ($discussion->exists || !$actor)
		{
			return;
		}

		$this->log->info(sprintf(
			'[AI Tagger] Listener invoked for new discussion with title: "%s".',
			$discussion->title
		));

		$content = Arr::get($event->data, 'attributes.content');
		if (empty($content))
		{
			$this->log->warning(sprintf(
				'[AI Tagger] Aborting job dispatch: No content found in the first post for discussion with title: "%s".',
				$discussion->title
			));
			return;
		}

		$title = $discussion->title;
		if (!$title)
		{
			return;
		}

		$combined_data = $title . ' ' . $content;

		$discussion->afterSave(function (Discussion $discussion) use ($actor, $combined_data) {
			$this->log->info(sprintf(
				'[AI Tagger] Dispatching GenerateTagsJob for discussion ID: %d.',
				$discussion->id
			));
			$job = new GenerateTagsJob($discussion->id, $actor->id, $combined_data);
			$this->bus->dispatch($job->onConnection('database'));
		});
	}
}