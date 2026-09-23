<?php
namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La version de assets de Inertia debe ser ESTABLE (config app.asset_version),
 * no el hash del manifest de Vite. Asi un despliegue de un build nuevo no expulsa
 * con 409/404 a los usuarios que tienen la pestana abierta.
 */
class InertiaVersionEstableTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_usa_config_y_no_el_manifest(): void
    {
        config()->set('app.asset_version', '7');
        $middleware = app(HandleInertiaRequests::class);

        $this->assertSame('7', $middleware->version(request()));
    }

    public function test_version_por_defecto_es_estable(): void
    {
        config()->set('app.asset_version', null);
        $middleware = app(HandleInertiaRequests::class);

        // Sin config explicita cae al valor por defecto '1' (estable), nunca null/hash.
        $this->assertSame('1', $middleware->version(request()));
    }
}
