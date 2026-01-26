<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Sale;
use App\Models\StockMovement;
use App\Observers\SaleObserver;
use App\Observers\StockMovementObserver;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\Providers\DevPixProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, DevPixProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        StockMovement::observe(StockMovementObserver::class);
        Sale::observe(SaleObserver::class);
    }
}
