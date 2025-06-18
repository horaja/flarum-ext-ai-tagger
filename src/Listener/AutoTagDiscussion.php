<?php

/*
 * This file is used in an extension to Flarum: The AI Tagger
 * Leverages Laravel's event system
 * Bridge between Flarum's internal event system and your external AI service
 * 
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with the source code
 */

namespace Horaja\AiTagger\Listener;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving;
use Horaja\AiTagger\Job\GenerateTagsJob;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;

class AutoTagDiscussion
{
	protected $bus;

	public function __construct(Dispatcher $bus)
	{
		$this->bus = $bus;
	}

	public function handle(Saving $event)
	{
		error_log('>>> AI Tagger Listener IS RUNNING! <<<');

		error_log('Event Data Structure: ' . print_r($event->data, true));
		
		$actor = $event->actor;
		$discussion = $event->discussion;

		if ($discussion->exists || !$actor)
		{
			error_log('Warning: discussion exists and/or actor error');
			return;
		}

		$content = Arr::get($event->data, 'attributes.content');
		if (!$content)
		{
			error_log('CUSTOM ERROR MESSAGE: no first post data');
			return;
		}

		$title = $discussion->title;
		if (!$title)
		{
			error_log('CUSTOM ERROR MESSAGE: no title data');
			return;
		}

		$combined_data = $title . ' ' . $content;
		$discussion->afterSave(function (Discussion $discussion) use ($actor, $combined_data) {
			$job = new GenerateTagsJob($discussion->id, $actor->id, $combined_data);
			error_log('>>> AI Tagger dispatch just got dispatched!! <<<');
			$this->bus->dispatch($job->onConnection('database'));
		});
	}
}