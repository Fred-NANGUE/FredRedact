<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) =>
            rtrim((string) config('services.frontend_url'), '/')
            .'/?token='.urlencode($token)
            .'&email='.urlencode($user->getEmailForPasswordReset())
        );
    }
}
