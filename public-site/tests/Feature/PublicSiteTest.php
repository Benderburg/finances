<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Noros\Cms\Models\Page;
use Noros\Cms\Models\Post;
use Noros\Cms\Support\LocalizedContent;
use Noros\Core\Models\Setting;
use Tests\TestCase;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Noros\Cms\Filament\Resources\PageResource\Pages\EditPage;
use Noros\Core\Models\Role;
use Noros\Core\Models\User;

class PublicSiteTest extends TestCase
{
    public function test_cms_locale_and_native_page_editor_preserve_product_blocks(): void
    {
        $this->seed(DatabaseSeeder::class);
        $response = $this->get('/cms/login?locale=en')->assertOk()->assertSee('lang="en"', false);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->get('/cms/login')->assertOk()->assertSee('lang="en"', false);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'administrator')->firstOrFail());
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('cms'));
        $page = Page::where('is_home', true)->firstOrFail();
        $before = $page->blocks;
        $component = Livewire::test(EditPage::class, ['record' => $page->id]);
        $keys = array_keys($component->get('data.blocks'));
        $component->set('data.blocks.'.$keys[0].'.data.title', 'CMS edited hero')
            ->call('save')->assertHasNoFormErrors();
        $after = $page->fresh()->blocks;
        $this->assertCount(count($before), $after);
        $this->assertSame('CMS edited hero', $after[0]['data']['title']);
        foreach ($before as $i => $block) {
            foreach ($block['data'] as $field => $value) {
                if ($i === 0 && $field === 'title') { continue; }
                $this->assertSame($value, $after[$i]['data'][$field], "Block $i field $field");
            }
        }
        $this->get('/ru')->assertOk()->assertSee('CMS edited hero');
    }

    public function test_localized_public_pages_have_seo_and_product_links(): void
    {
        $this->seed(DatabaseSeeder::class);
        foreach (['ru', 'ro', 'en'] as $locale) {
            foreach (['', '/features', '/about', '/privacy', '/terms', '/blog'] as $path) {
                $response = $this->get('/'.$locale.$path)->assertOk();
                $response->assertSee('lang="'.$locale.'"', false)
                    ->assertSee('rel="canonical"', false)
                    ->assertSee('property="og:image"', false)
                    ->assertSee('name="twitter:card"', false)
                    ->assertSee('contact@noros.net')
                    ->assertSee('Powered by Noros');
                $this->assertStringNotContainsString('site.nav', $response->getContent());
                $this->assertStringNotContainsString('site.start', $response->getContent());
                $response->assertCookieMissing('XSRF-TOKEN');
            }
            $this->get('/'.$locale.'/blog/budget-without-pressure')->assertOk()
                ->assertSee('BlogPosting')->assertSee('rel="canonical"', false);
        }
        $this->get('/')->assertRedirect('/ru');
        $this->get('/blog')->assertRedirect('/ru/blog');
        $this->get('/ru/missing')->assertNotFound()->assertSee('noindex');
        $this->get('/xx/features')->assertNotFound();
    }

    public function test_blog_publication_empty_state_and_long_title(): void
    {
        $this->seed(DatabaseSeeder::class);
        Post::query()->update(['status' => 'draft']);
        $this->get('/ru/blog')->assertOk()->assertSee(__('site.emptyBlog', locale: 'ru'));
        $post = Post::query()->firstOrFail();
        $post->update(['status' => 'published', 'title' => str_repeat('Очень длинный заголовок ', 12)]);
        $this->get('/ru/blog')->assertOk()->assertSee($post->title);
        $this->get('/ru/blog/'.$post->slug)->assertOk()->assertSee($post->title);
        $post->update(['published_at' => now()->addDay()]);
        $this->get('/ru/blog/'.$post->slug)->assertNotFound();
        $this->get('/ru')->assertOk()->assertDontSee($post->title);
    }

    public function test_sitemap_robots_and_editor_changes_are_preserved(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/ru/features')->assertSee('/ro/blog/budget-without-pressure');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /cms')->assertSee('sitemap.xml');
        $page = Page::where('slug', 'features')->firstOrFail();
        $page->update(['seo_title' => 'Edited title']);
        app(LocalizedContent::class)->save($page, 'en', ['seo_title' => 'Editor English title']);
        Setting::where('key', 'site.contact_email')->update(['value' => json_encode('editor@noros.net')]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('Edited title', $page->fresh()->getRawOriginal('seo_title'));
        $this->assertSame('Editor English title', $page->fresh()->translated('seo_title', 'en'));
        $this->assertSame('editor@noros.net', Setting::where('key', 'site.contact_email')->firstOrFail()->value);
        $this->get('/cms')->assertRedirect();
        $this->get('/cms/login')->assertOk();
    }

    public function test_shared_document_root_dispatch_keeps_application_routes(): void
    {
        $finance = require dirname(base_path()).'/backend/bootstrap/application-path.php';
        foreach (['/app', '/login', '/register', '/api/v1/me', '/auth/verify-email/id/hash', '/operations', '/sw.js', '/up', '/sanctum/csrf-cookie'] as $path) {
            $this->assertTrue($finance($path), $path);
        }
        foreach (['/', '/ru', '/en/blog', '/blog/article', '/cms/login', '/livewire/update', '/sitemap.xml', '/unknown', '/application'] as $path) {
            $this->assertFalse($finance($path), $path);
        }
    }
}
