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
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Messages\MailMessage;
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
        VerifyEmail::toMailUsing(function ($user, string $url) {
            $locale = DB::table('user_settings')->where('user_id', $user->id)->value('locale');
            [$subject, $greeting, $intro, $action, $expiry, $ignore] = match ($locale) {
                'ru' => ['Norocel: подтвердите email', 'Здравствуйте!', 'Подтвердите адрес почты, чтобы войти в Norocel и начать вести финансы.', 'Подтвердить email и войти', 'Ссылка действует 60 минут. После первого подтверждения вы войдёте в приложение.', 'Если вы не регистрировались в Norocel, просто проигнорируйте это письмо.'],
                'en' => ['Norocel: verify your email', 'Hello!', 'Verify your email address to sign in to Norocel and start managing your finances.', 'Verify email and sign in', 'This link expires in 60 minutes. Your first confirmation signs you in to the app.', 'If you did not create a Norocel account, you can ignore this email.'],
                default => ['Norocel: confirmă adresa de email', 'Bună!', 'Confirmă adresa de email pentru a intra în Norocel și a începe să îți gestionezi finanțele.', 'Confirmă emailul și intră', 'Linkul este valabil 60 de minute. Prima confirmare te autentifică în aplicație.', 'Dacă nu ai creat un cont Norocel, poți ignora acest mesaj.'],
            };

            return (new MailMessage)->subject($subject)->greeting($greeting)->line($intro)->action($action, $url)->line($expiry)->line($ignore)->salutation('Norocel');
        });
        RateLimiter::for('verification', fn ($request) => Limit::perMinute(1)->by($request->user()?->id ?? $request->ip()));
        ResetPassword::createUrlUsing(fn ($user, $token) => url('/reset-password').'?token='.urlencode($token).'&email='.urlencode($user->email));
        foreach (['auth' => 10, 'imports' => 5, 'reports' => 60] as $key => $limit) {
            RateLimiter::for($key, fn ($request) => Limit::perMinute($limit)->by(($request->user()?->id ?? $request->ip()).':'.$key));
        }
    }
}
