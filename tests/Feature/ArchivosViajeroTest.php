<?php
namespace Tests\Feature;

use App\Models\ArchivoViajero;
use App\Models\Empleados;
use App\Models\SolicitudViaticos;
use App\Models\ViajeroComision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivosViajeroTest extends TestCase
{
    use RefreshDatabase;

    private function viajero(): ViajeroComision
    {
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        return ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
    }

    public function test_viajero_tiene_archivos(): void
    {
        $this->seed();
        $v = $this->viajero();
        ArchivoViajero::create(['viajero_comision_id' => $v->id, 'tipo' => 'comprobante', 'path' => 'x/a.pdf', 'nombre' => 'a.pdf']);
        ArchivoViajero::create(['viajero_comision_id' => $v->id, 'tipo' => 'soporte', 'path' => 'x/b.pdf', 'nombre' => 'b.pdf']);

        $this->assertEquals(2, $v->fresh()->archivos()->count());
        $this->assertEquals(1, $v->fresh()->archivos()->where('tipo', 'comprobante')->count());
    }
}
