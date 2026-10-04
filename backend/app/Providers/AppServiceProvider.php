<?php

namespace App\Providers;

use App\Contracts\ReferenceRateProvider;
use App\Domain\BnmRateProvider;
use App\Models\FinancialRecord;
use App\Policies\FinancialRecordPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReferenceRateProvider::class, BnmRateProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(FinancialRecord::class, FinancialRecordPolicy::class);
        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]));
        ResetPassword::createUrlUsing(fn ($user, $token) => url('/reset-password').'?token='.urlencode($token).'&email='.urlencode($user->email));
        foreach (['auth' => 10, 'imports' => 5, 'reports' => 60] as $key => $limit) {
            RateLimiter::for($key, fn ($request) => Limit::perMinute($limit)->by(($request->user()?->id ?? $request->ip()).':'.$key));
        }
    }
}
