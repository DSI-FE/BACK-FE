<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Carbon\Carbon::setUTF8(true); // Configura UTF-8 para soportar caracteres especiales
        \Carbon\Carbon::setLocale(config('app.locale')); // Configura el locale de Carbon basado en el archivo de configuración
        setlocale(LC_TIME, config('app.locale'));
    }
}
