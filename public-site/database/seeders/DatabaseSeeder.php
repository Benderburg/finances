<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Noros\Cms\Models\Menu;
use Noros\Cms\Models\MenuItem;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\Post;
use Noros\Cms\Support\LocalizedContent;
use Noros\Core\Database\Seeders\CoreSeeder;
use Noros\Core\Models\Setting;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CoreSeeder::class);
        $data = json_decode(file_get_contents(database_path('content/site.json')), true, flags: JSON_THROW_ON_ERROR)['locales'];
        $base = config('noros.fallback_locale');
        $content = app(LocalizedContent::class);
        foreach (config('site') as $key => $value) {
            Setting::firstOrCreate(['key' => 'site.'.$key], ['value' => $value]);
        }
        $localizedSeo = [];
        foreach ($data as $locale => $localeData) {
            $home = $localeData['pages']['home'];
            $localizedSeo[$locale] = ['default_title' => $home['seo_title'], 'default_description' => $home['seo_description'], 'og_locale' => ['ru' => 'ru_MD', 'ro' => 'ro_MD', 'en' => 'en_US'][$locale]];
        }
        Setting::firstOrCreate(['key' => 'site.seo'], ['value' => [
            'site_name' => config('site.name'), 'default_title' => $data[$base]['pages']['home']['seo_title'],
            'default_description' => $data[$base]['pages']['home']['seo_description'],
            'default_image' => config('site.social_image'), 'robots' => 'index,follow',
            'twitter_card' => 'summary_large_image', 'require_translation' => true,
            'x_default_locale' => $base, 'organization_enabled' => false, 'sitemap_enabled' => true,
            'robots_rules' => "User-agent: *\nDisallow: /cms\nDisallow: /livewire\nDisallow: /app\nDisallow: /api\nDisallow: /auth\nDisallow: /login\nDisallow: /register\nDisallow: /admin\nDisallow: /backup\nDisallow: /accounts\nDisallow: /operations\nDisallow: /settings\nDisallow: /reports\nDisallow: /goals\nDisallow: /savings\nDisallow: /budgets\nDisallow: /liabilities\nDisallow: /categories\nDisallow: /csv\nDisallow: /build\n",
            'locales' => $localizedSeo,
        ]]);
        $pages = [];
        foreach ($data[$base]['pages'] as $slug => $fields) {
            $pages[$slug] = $page = Page::firstOrCreate(['slug' => $slug], $fields + [
                'is_home' => $slug === 'home', 'template' => $slug === 'home' ? 'landing' : 'standard',
                'status' => 'published', 'published_at' => '2026-10-04 09:00:00',
                'og_image' => config('site.social_image'), 'seo_robots' => 'index,follow',
            ]);
            foreach ($data as $locale => $localeData) {
                if ($locale !== $base) {
                    $this->missingTranslation($content, $page, $locale, ['slug' => $slug] + $localeData['pages'][$slug]);
                }
            }
        }
        foreach ($data[$base]['posts'] as $index => $fields) {
            $post = Post::firstOrCreate(['slug' => $fields['slug']], $fields + [
                'status' => 'published', 'published_at' => '2026-10-04 '.sprintf('%02d:00:00', 9 - $index),
                'allow_comments' => false, 'allow_ratings' => false, 'og_type' => 'article',
            ]);
            foreach ($data as $locale => $localeData) {
                if ($locale !== $base) {
                    $translated = $localeData['posts'][$index];
                    unset($translated['cover_image']);
                    $this->missingTranslation($content, $post, $locale, $translated);
                }
            }
        }
        foreach (['header' => ['features', 'how', 'blog', 'about'], 'footer' => ['features', 'blog', 'about', 'privacy', 'terms']] as $location => $entries) {
            $menu = Menu::firstOrCreate(['location' => $location], ['name' => ucfirst($location), 'is_active' => true]);
            foreach ($entries as $index => $slug) {
                $key = 'site.nav'.ucfirst($slug);
                $item = MenuItem::firstOrCreate(['menu_id' => $menu->id, 'code' => $location.'.'.$slug], [
                    'label' => __($key, locale: $base), 'type' => $slug === 'blog' ? 'blog_index' : ($slug === 'how' ? 'custom' : 'page'),
                    'page_id' => $pages[$slug]->id ?? null, 'url' => $slug === 'how' ? '/#how-it-works' : null,
                    'target' => '_self', 'sort_order' => $index * 10, 'is_active' => true,
                ]);
                foreach (array_keys($data) as $locale) {
                    if ($locale !== $base) {
                        $this->missingTranslation($content, $item, $locale, ['label' => __($key, locale: $locale)]);
                    }
                }
            }
        }
    }

    private function missingTranslation(LocalizedContent $content, $model, string $locale, array $fields): void
    {
        $missing = array_diff_key($fields, $content->fields($model, $locale));
        if ($missing !== []) {
            $content->save($model, $locale, $missing);
        }
    }
}
