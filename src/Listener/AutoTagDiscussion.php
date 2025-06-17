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

use Flarum\Discussion\Event\Saving;
use Flarum\Tags\TagRepository;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Arr;

class AutoTagDiscussion
{
	// what's the point of this line...
	protected $tags;

	public function __construct(TagRepository $service)
	{
		$this->tags = $service;
	}

	public function handle(Saving $event)
	{
		error_log('>>> AI Tagger Listener IS RUNNING! <<<');

		error_log('Event Data Structure: ' . print_r($event->data, true));
		
		$actor = $event->actor;
		$discussion = $event->discussion;
		$user_tags = Arr::get($event->data, 'relationships.tags.data', []);
		$user_tag_ids = array_column($user_tags, 'id');
		// do we need to check if discussion exists?
		if ($discussion->exists || !$actor)
		{
			error_log('CUSTOM ERROR MESSAGE: discussion exists and/or actor error');
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
		$suggested_tags = $this->getSuggestedTags($combined_data);
		if (empty($suggested_tags))
		{
			error_log('CUSTOM ERROR MESSAGE: no suggested tags from model');
			return;
		}

		// $searchQuery = implode(' ', $suggested_tags);
		// $criteria = new Criteria($actor, $searchQuery);
		// $tag_ids = $this->searcher
		// 	->search($criteria)
		// 	->getResults()
		// 	->filter(function ($tag) use ($suggested_tags) {
		// 		return in_array($tag->name, $suggested_tags);
		// 	})
		// 	->pluck('id')
		// 	->all();
		$ai_tag_ids = $this->tags->query()
			->whereIn('name', $suggested_tags)
			->whereVisibleTo($actor)
			->pluck('id')
			->all();
		
		if (empty($ai_tag_ids))
		{
			error_log('CUSTOM ERROR MESSAGE: no filtered tag ids');
			return;
		}

		$tag_ids = array_unique(array_merge($user_tag_ids, $ai_tag_ids));
		if (empty($tag_ids))
		{
			error_log('CUSTOM ERROR MESSAGE: no unique tag ids');
			return;
		}

		$discussion->afterSave(function ($discussion) use ($tag_ids) {
			error_log('>>> Syncing...');
			$discussion->tags()->sync($tag_ids);
		});
	}

	protected function getSuggestedTags(string $content) : array
	{
		$ai_backend_url = 'http://ai_backend:5000/api/v1/suggest-tags';

		// implement a try-catch block here during staging for better debugging
		try
		{
			$client = new HttpClient(['timeout' => 280.0]);
			$response = $client->post($ai_backend_url, ['json' => ['content' => $content]]);
			$data = json_decode($response->getBody()->getContents(), true);
	
			return $data['suggested_tags'] ?? [];
		} catch (\Exception $e)
		{
			# log error message
			error_log('AI Tagger HTTP Error: ' . $e->getMessage());
			return [];
		}
	}

}