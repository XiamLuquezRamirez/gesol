<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Sesion/CSRF expirada (419): tras mucho tiempo inactiva, la pantalla manda un
     * token viejo. En vez de la pagina generica de Laravel en ingles, se muestra una
     * pantalla propia de GeSol invitando a reingresar. Se intercepta en render()
     * (mas determinista que renderable()) antes del manejo por defecto.
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof TokenMismatchException) {
            return $this->respuestaSesionExpirada($request);
        }

        return parent::render($request, $e);
    }

    /**
     * Respuesta ante una sesion expirada (419), adaptada al tipo de peticion:
     * - Peticion AJAX/JSON (p. ej. el polling de notificaciones): responde 419 en JSON
     *   para que el cliente lo maneje (refrescar token y reintentar) sin romper la UI.
     * - Inertia y navegacion normal: redirige a la pantalla propia 'sesion-expirada'.
     *   Con Inertia, un redirect 302 a una ruta GET se sigue como visita completa,
     *   asi que la pagina propia se muestra correctamente dentro del diseno de la app.
     */
    private function respuestaSesionExpirada(Request $request): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(
                ['message' => 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.'],
                419 // Page Expired (sin constante en esta version de Symfony)
            );
        }

        return redirect()->route('sesion.expirada');
    }
}
