<?php

/*
 * This file is part of horaja/flarum-ext-ai-tagger.
 *
 * Author: Husain Raja
 * Email: horaja@cs.cmu.edu
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('jobs', function (Blueprint $table) {
	$table->bigIncrements('id');
	$table->string('queue')->index();
	$table->longText('payload');
	$table->unsignedTinyInteger('attempts');
	$table->unsignedInteger('reserved_at')->nullable();
	$table->unsignedInteger('available_at');
	$table->unsignedInteger('created_at');
});