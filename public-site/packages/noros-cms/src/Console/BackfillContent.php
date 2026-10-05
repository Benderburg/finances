<?php

namespace Noros\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Noros\Cms\Models\Category;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioProject;
use Noros\Cms\Models\Post;
use Noros\Cms\Models\Tag;
use Noros\Cms\Support\EngagementService;
use Noros\Cms\Support\LocalizedContent;
use Noros\Core\Support\TranslationLibrary;

class BackfillContent extends Command
{
    protected $signature = 'noros:content:backfill {--dry-run : Report counts without writing}';

    protected $description = 'Index existing localized URLs and attach legacy blog comments without changing content';

    public function handle(LocalizedContent $content): int
    {
        foreach ([Page::class, Post::class, PortfolioProject::class, Category::class, Tag::class] as $modelClass) {
            $this->line(class_basename($modelClass).': '.$modelClass::count());
            if (! $this->option('dry-run')) {
                $modelClass::query()->chunkById(100, function ($models) use ($content): void {
                    DB::transaction(function () use ($models, $content): void {
                        app(TranslationLibrary::class)->lockForWriting();
                        foreach ($models as $model) {
                            $content->reindex($model);
                        }
                    });
                });
            }
        }
        if (! $this->option('dry-run')) {
            $this->line('Comments attached: '.app(EngagementService::class)->backfillLegacyComments());
        }

        return self::SUCCESS;
    }
}
