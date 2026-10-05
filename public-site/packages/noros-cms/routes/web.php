<?php

use Illuminate\Support\Facades\Route;
use Noros\Cms\Http\Controllers\BlogCommentController;
use Noros\Cms\Http\Controllers\BlogController;
use Noros\Cms\Http\Controllers\ContactFormController;
use Noros\Cms\Http\Controllers\EngagementController;
use Noros\Cms\Http\Controllers\PageController;
use Noros\Cms\Http\Controllers\PortfolioController;
use Noros\Cms\Http\Controllers\PostRatingController;
use Noros\Cms\Http\Controllers\RobotsController;
use Noros\Cms\Http\Controllers\SitemapController;
use Noros\Core\Http\Middleware\SetLocale;

Route::middleware('web')->group(function (): void {
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('robots');
    Route::prefix('{locale}')->whereIn('locale', array_keys(config('noros.supported_locales')))->middleware(SetLocale::class)->group(function (): void {
        Route::post('/cms/contact', ContactFormController::class)->middleware('throttle:5,1')->name('noros-cms.contact.store');
        if (config('noros-cms.features.comments')) {
            Route::get('/engagement/{type}/{id}/comments', [EngagementController::class, 'index'])->whereNumber('id')->middleware('throttle:120,1')->name('engagement.comments.index');
            Route::post('/engagement/{type}/{id}/comments', [EngagementController::class, 'comment'])->whereNumber('id')->middleware('throttle:5,1')->name('engagement.comments.store');
        }
        if (config('noros-cms.features.ratings')) {
            Route::post('/engagement/{type}/{id}/rating', [EngagementController::class, 'rate'])->whereNumber('id')->middleware('throttle:30,1')->name('engagement.ratings.store');
        }
        if (config('noros-cms.features.blog')) {
            Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
            Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
            if (config('noros-cms.features.comments')) {
                Route::post('/blog/{slug}/comments', [BlogCommentController::class, 'store'])->middleware('throttle:5,1')->name('blog.comments.store');
            }
            if (config('noros-cms.features.ratings')) {
                Route::post('/blog/{slug}/rating', [PostRatingController::class, 'toggle'])->middleware('throttle:30,1')->name('blog.rating.toggle');
            }
        }
        if (config('noros-cms.features.portfolio')) {
            Route::get('/portfolio/{case}', [PortfolioController::class, 'show'])->name('portfolio.case');
        }
        if (config('noros-cms.features.pages')) {
            Route::get('/', [PageController::class, 'home'])->name('home');
            Route::get('/contacts', [PageController::class, 'contacts'])->name('contacts');
            // Registered as a fallback so downstream modules (e.g. shop) retain route priority.
            Route::get('/{path}', [PageController::class, 'show'])->where('path', '.+')->fallback()->name('pages.show');
        }
    });
});
