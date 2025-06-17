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

return [
    (new Extend\Event)
			->listen(Saving::class, Listener\AutoTagDiscussion::class)
];