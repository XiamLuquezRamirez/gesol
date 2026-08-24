<?php
namespace Tests\Feature;

use App\Enums\Rubro;
use App\Models\{AsignacionViatico, Empleados, SolicitudViaticos, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubroTransporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_transporte_existe_como_tarifa(): void
    {
        $this->seed();
        $this->assertDatabaseHas('tarifas_viaticos', ['rubro' => 'transporte']);
        $this->assertTrue(Rubro::tryFrom('transporte') === Rubro::Transporte);
    }

    public function test_se_puede_asignar_transporte_a_un_viajero(): void
    {
        $this->seed();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $a = AsignacionViatico::create([
            'viajero_comision_id' => $v->id, 'rubro' => 'transporte',
            'valor_unitario' => 30000, 'dias' => 2,
        ]);
        $this->assertEquals(Rubro::Transporte, $a->fresh()->rubro);
        $this->assertEquals(60000, $a->fresh()->subtotal);
    }
}
