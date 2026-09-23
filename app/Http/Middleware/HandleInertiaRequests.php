<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Version de assets de Inertia.
     *
     * Por defecto Inertia usa el hash del manifest de Vite, que cambia en CADA
     * compilacion. En un despliegue en subcarpeta (produccion) eso provoca que,
     * si el usuario tiene una pestana abierta cuando se sube un build nuevo, la
     * siguiente visita XHR reciba un 409 y fuerce una recarga dura; si esa
     * recarga con query-string no resuelve bien tras el recorte de prefijo del
     * servidor, el usuario ve un 404 intermitente.
     *
     * Fijamos la version a un valor estable (configurable por ASSET_VERSION en el
     * .env). Asi los despliegues no expulsan al usuario con un 409/404: la app
     * sigue funcionando y el bundle nuevo se toma en la proxima carga natural.
     * Para forzar deliberadamente la recarga de todos los clientes tras un cambio
     * incompatible, basta subir el valor de ASSET_VERSION en produccion.
     */
    public function version(Request $request): string|null
    {
        return (string) (config('app.asset_version') ?: '1');
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->load('roles'),
            ],
            'notificaciones_no_leidas' => $request->user()
                ?->unreadNotifications()->count() ?? 0,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ];
    }
}
