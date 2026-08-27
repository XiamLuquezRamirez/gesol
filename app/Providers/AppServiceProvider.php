<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Despliegue en subcarpeta: si APP_URL incluye un path (p. ej.
        // https://ingeer.co/gesol), forzar que TODAS las URLs generadas
        // (route(), url(), redirects, assets) lleven ese prefijo. El index.php
        // de subcarpeta se encarga de quitar el prefijo del REQUEST_URI de
        // entrada; esto asegura la coherencia en la salida. En local (APP_URL
        // sin path) no hace nada.
        $appUrl = config('app.url');
        $path = parse_url($appUrl, PHP_URL_PATH);
        if ($path && trim($path, '/') !== '') {
            URL::forceRootUrl($appUrl);
            // Si APP_URL es https, forzar el esquema para evitar URLs http
            // (mixed content) detras del proxy/SSL del hosting.
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
