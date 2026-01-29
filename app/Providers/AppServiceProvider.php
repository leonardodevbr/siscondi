<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
        // Super-admin tem acesso total; admin mantém checagem por permissão
        Gate::before(function ($user, $ability) {
            if ($user && $user->hasRole('super-admin')) {
                return true;
            }
            return null;
        });
    }
}
