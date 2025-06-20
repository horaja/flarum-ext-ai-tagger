# Flarum AI Tagger Extension

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

An extension for the [Flarum forum software](https://flarum.org/) that automatically suggests and applies relevant tags to new discussions using an external AI backend service.

This extension is designed to improve content organization and discoverability by analyzing the title and content of a new post to determine the most relevant topics.

## Key Features

- **Asynchronous Tagging:** Leverages Flarum's queue system to process tag suggestions in the background, ensuring no delay for the user creating the post.
- **Intelligent Tag Merging:** Combines AI-suggested tags with any tags manually chosen by the user, without creating duplicates.
- **Seamless, Asynchronous Integration:** Hooks directly into Flarum's discussion-saving event to trigger the AI analysis asynchronously with a dedicated Job Queue.

## Requirements

- Flarum ^1.8.0
- A running instance of the compatible [Forum AI Backend Service](https://github.com/horaja/forum-ai-backend) accessible from the Flarum server.

## Installation

1.  From your Flarum root directory, use Composer to install the extension:
    ```bash
    composer require horaja/flarum-ext-ai-tagger
    ```
2.  Log in to your Flarum admin panel, navigate to the "Extensions" page, and enable the "AI Tagger" extension.

## Configuration

Currently, the URL for the AI backend service is hardcoded in `src/Job/GenerateTagsJob.php`.

```php
$ai_backend_url = 'http://ai_backend:5000/api/v1/suggest-tags';