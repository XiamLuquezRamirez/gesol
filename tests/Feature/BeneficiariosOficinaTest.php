<?php
namespace Tests\Feature;

use App\Models\{Empleados, SolicitudOficina};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiariosOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_solicitud_puede_tener_varios_empleados_beneficiarios(): void
    {
        $this->seed();
        $cabecera = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
        ]);
        $ids = Empleados::take(2)->pluck('id')->all();

        $cabecera->beneficiarios()->sync($ids);

        $this->assertEquals(2, $cabecera->fresh()->beneficiarios()->count());
        $this->assertEqualsCanonicalizing($ids, $cabecera->fresh()->beneficiarios->pluck('id')->all());
    }
}
