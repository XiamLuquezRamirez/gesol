<?php
namespace Tests\Feature;

use App\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Ante un 419 (token CSRF/sesion expirada) la app no debe mostrar la pagina
 * generica de Laravel, sino una respuesta acorde al tipo de peticion:
 *  - Inertia  -> redirect a la pantalla propia 'sesion-expirada' (visita completa)
 *  - JSON     -> 419 con mensaje (para el polling que refresca token y reintenta)
 *  - normal   -> redirect a la pantalla propia 'sesion-expirada'
 */
class SesionExpiradaTest extends TestCase
{
    private function handler(): Handler
    {
        return app(ExceptionHandler::class);
    }

    public function test_peticion_inertia_redirige_a_pantalla_propia(): void
    {
        $request = Request::create('/parametros', 'POST');
        $request->headers->set('X-Inertia', 'true');

        $response = $this->handler()->render($request, new TokenMismatchException());

        // Redirect a la pantalla propia; Inertia lo sigue como visita completa.
        $this->assertTrue($response->isRedirect(route('sesion.expirada')));
    }

    public function test_peticion_json_responde_419_con_mensaje(): void
    {
        $request = Request::create('/notificaciones', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $this->handler()->render($request, new TokenMismatchException());

        $this->assertSame(419, $response->getStatusCode());
        $mensaje = json_decode($response->getContent(), true)['message'] ?? '';
        $this->assertStringContainsString('sesión expiró', $mensaje);
    }

    public function test_peticion_normal_redirige_a_pantalla_propia(): void
    {
        $request = Request::create('/parametros', 'POST');

        $response = $this->handler()->render($request, new TokenMismatchException());

        $this->assertTrue($response->isRedirect(route('sesion.expirada')));
    }

    public function test_la_ruta_de_pantalla_propia_es_publica_y_renderiza(): void
    {
        // Sin autenticacion (la sesion expiro) debe poder verse.
        $this->get(route('sesion.expirada'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Errores/SesionExpirada'));
    }
}
