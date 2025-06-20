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
use Psr\Log\LoggerInterface;

class GenerateTagsJob extends AbstractJob
{
	/**
	 * @var int
	 */
	protected $discussionId;

	/**
	 * @var int
	 */
	protected $actorId;

	/**
	 * @var string
	 */
	protected $content;

	/**
	 * @param int $discussionId The ID of the discussion to tag.
	 * @param int $actorId The ID of the user performing the action.
	 * @param string $content The text content to be analyzed.
	 */
	function __construct(int $discussionId, int $actorId, string $content)
	{
		$this->discussionId = $discussionId;
		$this->actorId = $actorId;
		$this->content = $content;
	}

	/**
	 * Execute the job.
	 * 
	 * Fetches tags from the AI service, finds corresponding tag IDs in Flarum,
	 * merges them with user-selected tags, and syncs them to the discussion.
	 * 
	 * @param TagRepository $tags The Flarum tag repository.
	 * @param DiscussionRepository $discussions The Flarum discussion repository.
	 * @param LoggerInterface $log The Flarum logger.
	 * @return void
	 */
	function handle(TagRepository $tags, DiscussionRepository $discussions, LoggerInterface $log)
	{
		$log->info(sprintf('[AI Tagger] GenerateTagsJob running for discussion ID: %d.', $this->discussionId));

		$suggested_tags = $this->getSuggestedTags($this->content, $log);
		if (empty($suggested_tags))
		{
			return;
		}

		$ai_tag_ids = $tags->query()
			->whereIn('name', $suggested_tags)
			->pluck('id')
			->all();
		
		if (empty($ai_tag_ids))
		{
			$log->warning(sprintf(
				'[AI Tagger] No Flarum tags found matching the AI suggestions: ["%s"] for discussion ID: %d.',
				implode('", "', $suggested_tags),
				$this->discussionId
			));
			return;
		}

		$actor = User::find($this->actorId);
		$discussion = $discussions->findOrFail($this->discussionId, $actor);
		$user_tag_ids = $discussion->tags()->get()->pluck('id')->all();

		$tag_ids = array_unique(array_merge($ai_tag_ids, $user_tag_ids));

		$discussion->tags()->sync($tag_ids);

		$log->info(sprintf(
			'[AI Tagger] Successfully synced tags for discussion ID: %d.',
			$this->discussionId
		));
	}

	/**
	 * Fetches suggested tags from the external AI backend service.
	 * 
	 * Makes an HTTP POST request to the AI service, sending the discussion
	 * content and returning an array of suggested tag names.
	 * 
	 * @param string $content The text content to analyze.
	 * @param LoggerInterface $log The logger instance.
	 * @return string[] An array of suggested tag names. Returns an empty array on failure.
	 */
	protected function getSuggestedTags(string $content, LoggerInterface $log) : array
	{
		$ai_backend_url = 'http://ai_backend:5000/api/v1/suggest-tags';

		try
		{
			$client = new HttpClient(['timeout' => 280.0]);
			$response = $client->post($ai_backend_url, ['json' => ['content' => $content]]);
			$data = json_decode($response->getBody()->getContents(), true);
			
			if (empty($data['suggested_tags'])) {
				$log->info(sprintf(
						'[AI Tagger] AI service returned no suggested tags for discussion ID: %d.',
						$this->discussionId
				));
				return [];
			}

			return $data['suggested_tags'];

		} catch (\Exception $e)
		{
			$log->error(sprintf(
        '[AI Tagger] HTTP request to AI backend failed for discussion ID: %d. Error: %s',
        $this->discussionId,
        $e->getMessage()
			));
			return [];
		}
	}
}

