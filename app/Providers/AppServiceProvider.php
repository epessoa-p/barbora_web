<?php

namespace App\Providers;

use App\Support\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tenancy::class, function ($app) {
            $tenancy = new Tenancy();

            // En consola (artisan, seeders, colas) no hay empresa activa y el
            // fail-closed dejaría todo vacío. En tests SÍ arranca cerrado: los
            // tests de aislamiento fijan su propia empresa y deben ser reales.
            if ($app->runningInConsole() && ! $app->runningUnitTests()) {
                $tenancy->unrestrict();
            }

            return $tenancy;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
