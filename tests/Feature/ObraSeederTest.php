<?php
namespace Tests\Feature;

use App\Models\TipoSolicitud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObraSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rol_residente_existe(): void
    {
        $this->seed();
        $this->assertTrue(Role::where('name', 'residente')->exists());
    }

    public function test_tipo_obr_existe_con_estado_inicial(): void
    {
        $this->seed();
        $obr = TipoSolicitud::where('clave', 'OBR')->first();
        $this->assertNotNull($obr);
        $this->assertSame('borrador', $obr->estado_inicial);
        $enviar = collect($obr->transiciones)->firstWhere('accion', 'enviar');
        $this->assertContains('residente', $enviar['roles']);
        $this->assertContains('lider_area', $enviar['roles']);
    }
}
