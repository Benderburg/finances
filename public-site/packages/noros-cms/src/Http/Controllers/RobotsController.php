<?php

namespace Noros\Cms\Http\Controllers;

use Illuminate\Http\Response;
use Noros\Core\Support\SiteSeo;

class RobotsController extends Controller
{
    public function __invoke(SiteSeo $seo): Response
    {
        $settings = $seo->settings();
        $rules = trim(str_replace("\r\n", "\n", (string) $settings['robots_rules']));
        if ($settings['sitemap_enabled']) {
            $rules .= "\n\nSitemap: ".route('sitemap');
        }

        return response($rules."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
