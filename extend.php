<?php

/*
 * This file is part of horaja/flarum-ext-ai-tagger.
 *
 * Author: Husain Raja
 * Email: horaja@cs.cmu.edu
 */

namespace Horaja\AiTagger;

use Flarum\Discussion\Event\Saving;
use Flarum\Extend;
use Horaja\AiTagger\Console\WorkQueueCommand;
use Horaja\AiTagger\Listener\AutoTagDiscussion;

return [
    (new Extend\Event())
			->listen(Saving::class, AutoTagDiscussion::class),

		(new Extend\Console())
			->command(WorkQueueCommand::class),
];