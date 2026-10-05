<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Noros\Cms\Enums\PageTemplate;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\PortfolioWidget;
use Noros\Cms\Models\PricingWidget;
use Noros\Cms\Support\BlogSidebarData;
use Noros\Cms\Support\LocalizedContent;

class PageController extends Controller
{
    public function home(BlogSidebarData $sidebarData): View
    {
        $page = Schema::hasTable('pages')
            ? Page::query()->published()->where('is_home', true)->first()
            : null;

        return $page ? $this->render($page, $sidebarData) : view(config('noros-cms.legacy_views.home', 'noros-cms::home'));
    }

    public function show(string $path, BlogSidebarData $sidebarData): View|Response|RedirectResponse
    {
        $normalizedPath = trim($path, '/');
        $page = Schema::hasTable('pages')
            ? app(LocalizedContent::class)->find(Page::class, $normalizedPath)
            : null;

        if ($page) {
            return $this->render($page, $sidebarData);
        }

        $redirect = app(LocalizedContent::class)->redirect(Page::class, $normalizedPath, app()->getLocale());
        if ($redirect) {
            return redirect($redirect->url, 301);
        }
        $legacyViews = config('noros-cms.legacy_views', []);

        abort_unless(isset($legacyViews[$normalizedPath]), 404);

        return view($legacyViews[$normalizedPath]);
    }

    public function contacts(BlogSidebarData $sidebarData): View|Response|RedirectResponse
    {
        return $this->show('contacts', $sidebarData);
    }

    private function render(Page $page, BlogSidebarData $sidebarData): View
    {
        $page->load('parent.parent.parent.parent.parent');
        $blocks = collect($page->blocks ?? []);

        $portfolioWidgets = $this->loadWidgets($blocks, 'portfolio_widget', PortfolioWidget::class);
        $pricingWidgets = $this->loadWidgets($blocks, 'pricing_widget', PricingWidget::class);

        $data = compact('page', 'portfolioWidgets', 'pricingWidgets');

        if ($page->template === PageTemplate::Sidebar) {
            $data += $sidebarData->get();
        }

        return view(config('noros-cms.views.page'), $data);
    }

    /** @param Collection<int, mixed> $blocks */
    private function loadWidgets(Collection $blocks, string $type, string $model): Collection
    {
        $ids = $blocks
            ->where('type', $type)
            ->pluck('data.widget_id')
            ->filter()
            ->unique()
            ->values();

        return $model::query()
            ->where('is_active', true)
            ->whereKey($ids)
            ->get()
            ->keyBy('id');
    }
}
