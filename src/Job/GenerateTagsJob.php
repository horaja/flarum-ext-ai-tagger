<?php

/*
 * This file is part of horaja/flarum-ext-ai-tagger.
 *
 * Author: Husain Raja
 * Email: horaja@cs.cmu.edu
 */

namespace Horaja\AiTagger\Job;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Queue\AbstractJob;
use Flarum\Tags\TagRepository;
use Flarum\User\User;
use GuzzleHttp\Client as HttpClient;

class GenerateTagsJob extends AbstractJob
{
	protected $discussionId;
	protected $actorId;
	protected $content;

	function __construct(int $discussionId, int $actorId, string $content)
	{
		$this->discussionId = $discussionId;
		$this->actorId = $actorId;
		$this->content = $content;
	}

	function handle(TagRepository $tags, DiscussionRepository $discussions)
	{
		error_log('>>> AI Tagger JOB GENERATOR IS RUNNING! <<<');

		$suggested_tags = $this->getSuggestedTags($this->content);
		if (empty($suggested_tags))
		{
			error_log("Warning: No output from Text Classification Model");
			return;
		}

		$ai_tag_ids = $tags->query()
			->whereIn('name', $suggested_tags)
			->pluck('id')
			->all();
		if (empty($ai_tag_ids))
		{
			error_log("Error: No matching tags found in Flarum");
			return;
		}

		$actor = User::find($this->actorId);
		$discussion = $discussions->findOrFail($this->discussionId, $actor);
		$user_tag_ids = $discussion->tags()->get()->pluck('id')->all();

		$tag_ids = array_unique(array_merge($ai_tag_ids, $user_tag_ids));

		$discussion->tags()->sync($tag_ids);
	}

	protected function getSuggestedTags(string $content) : array
	{
		$ai_backend_url = 'http://ai_backend:5000/api/v1/suggest-tags';

		try
		{
			$client = new HttpClient(['timeout' => 280.0]);
			$response = $client->post($ai_backend_url, ['json' => ['content' => $content]]);
			$data = json_decode($response->getBody()->getContents(), true);
	
			return $data['suggested_tags'] ?? [];
		} catch (\Exception $e)
		{
			error_log('AI Tagger HTTP Error: ' . $e->getMessage());
			return [];
		}
	}
}

