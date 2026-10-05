<?php

use Noros\Cms\Models\Post;
use Noros\Cms\Support\NoEngagementChallenge;

return [
    'contact' => ['enabled' => env('CMS_CONTACT_ENABLED', false), 'recipient' => env('CMS_CONTACT_EMAIL')],
    'engagement' => ['models' => ['post' => Post::class], 'moderation_email' => env('CMS_MODERATION_EMAIL'),
        'scales' => ['stars_5' => ['min' => 1, 'max' => 5]],
        'challenge' => NoEngagementChallenge::class],
    'features' => ['pages' => true, 'blog' => true, 'comments' => true, 'ratings' => true, 'portfolio' => true, 'menus' => true],
    'views' => ['page' => 'noros-cms::pages.show', 'blog_index' => 'noros-cms::blog.index', 'blog_show' => 'noros-cms::blog.show', 'portfolio' => 'noros-cms::portfolio.show'],
    'legacy_views' => [],
];
