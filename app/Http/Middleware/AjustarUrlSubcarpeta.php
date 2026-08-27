<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Despliegue en subcarpeta (p. ej. /gesol). El index.php de la raiz recorta el
 * prefijo del REQUEST_URI para que el routing de Laravel funcione, pero eso hace
 * que Inertia reporte la URL de la pagina SIN el prefijo (/login en vez de
 * /gesol/login), y el cliente cambia la barra sacando al usuario de la subcarpeta.
 *
 * Este middleware reinyecta el prefijo (derivado de APP_URL) en la URL que
 * Inertia comparte con el cliente, tanto en la respuesta HTML inicial (data-page)
 * como en las respuestas JSON de las visitas XHR. En local (APP_URL sin path) no
 * hace nada.
 */
class AjustarUrlSubcarpeta
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $prefijo = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        if ($prefijo === '' || $prefijo === '/') {
            return $response;
        }

        // Respuesta JSON de Inertia (visitas XHR): { "url": "/login", ... }
        if ($request->header('X-Inertia') && $response->headers->get('content-type') === 'application/json') {
            $data = json_decode($response->getContent(), true);
            if (is_array($data) && isset($data['url']) && ! str_starts_with($data['url'], $prefijo)) {
                $data['url'] = $prefijo . $data['url'];
                $response->setContent(json_encode($data));
            }
            return $response;
        }

        // Respuesta HTML inicial: el <div id="app" data-page="{...&quot;url&quot;:&quot;/login&quot;...}">
        $content = $response->getContent();
        if (is_string($content) && str_contains($content, 'data-page')) {
            // data-page trae el JSON con las entidades HTML escapadas (&quot;).
            $buscar = '&quot;url&quot;:&quot;';
            $pos = strpos($content, $buscar);
            if ($pos !== false) {
                $inicio = $pos + strlen($buscar);
                // Solo prefijar si aun no lo tiene.
                $yaConPrefijo = substr($content, $inicio, strlen($prefijo)) === $prefijo;
                if (! $yaConPrefijo) {
                    $content = substr($content, 0, $inicio) . $prefijo . substr($content, $inicio);
                    $response->setContent($content);
                }
            }
        }

        return $response;
    }
}
