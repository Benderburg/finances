<?php

namespace Noros\Cms\Support;

use Illuminate\Support\Collection;
use Noros\Cms\Models\Menu;
use Noros\Core\Support\CacheService;

class SiteNavigation
{
    public function menus(): Collection
    {
        if (! config('noros-cms.features.menus')) {
            return collect();
        }

        $menus = app(CacheService::class)->remember('cms', 'menus:'.app()->getLocale(), fn () => Menu::query()->where('is_active', true)->with(['rootItems.page', 'rootItems.post', 'rootItems.children.page', 'rootItems.children.post', 'rootItems.children.children.page', 'rootItems.children.children.post'])->get()->keyBy('location'), min(60, (int) config('noros-cache.ttls.cms', 300)));
        app(LocalizedContent::class)->warm($menus);

        return $menus;
    }
}
