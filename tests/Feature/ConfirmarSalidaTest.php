<?php
namespace Tests\Feature;

use App\Models\{Empleados, SolicitudViaticos, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmarSalidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_salida_confirmada_por_defecto_false(): void
    {
        $this->seed();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $this->assertFalse($v->fresh()->salida_confirmada);
    }
}
