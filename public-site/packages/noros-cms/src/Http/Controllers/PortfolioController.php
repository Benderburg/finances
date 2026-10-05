<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Noros\Cms\Models\PortfolioProject;
use Noros\Cms\Support\LocalizedContent;

class PortfolioController extends Controller
{
    public function show(string $case, LocalizedContent $content): View|RedirectResponse
    {
        $project = $content->find(PortfolioProject::class, $case);
        if (! $project) {
            $redirect = $content->redirect(PortfolioProject::class, $case, app()->getLocale());
            if ($redirect) {
                return redirect($redirect->url, 301);
            }
            abort(404);
        }
        $relatedCases = PortfolioProject::query()->published()->whereIn('slug', $project->related_slugs ?? [])->get()->map(fn ($item): array => $this->caseData($item))->all();

        return view(config('noros-cms.views.portfolio'), ['project' => $project, 'case' => $this->caseData($project), 'relatedCases' => $relatedCases]);
    }

    private function caseData(PortfolioProject $project): array
    {
        $data = [];
        foreach (['slug', 'title', 'heading', 'category', 'summary', 'content', 'image'] as $key) {
            $data[$key] = $project->{$key};
        }

        return $data + ['description' => $project->seo_description, 'keywords' => $project->seo_keywords, 'url' => $project->url];
    }
}
