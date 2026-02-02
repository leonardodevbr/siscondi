<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        // Garante que o diretório de importação de servidores existe (evita 403 por falta de permissão no path)
        try {
            Storage::disk('local')->makeDirectory('imports/servants');
        } catch (\Throwable) {
            // ignora se não tiver permissão (ex.: em ambiente read-only)
        }

        Gate::before(function ($user, $ability) {
            // Super-admin sempre tem acesso a tudo, independente de cache ou permissões no banco
            if ($user && method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }

            return null; // deixa o Gate seguir com as permissões do Spatie para os demais
        });
    }
}
