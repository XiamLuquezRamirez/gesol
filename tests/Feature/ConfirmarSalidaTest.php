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

    private function comisionConViajero(): array
    {
        $tipo = \App\Models\TipoSolicitud::where('clave','VIA')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $s = \App\Models\Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => \App\Models\Usuario::first()->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => 'enviada', 'radicado' => \App\Models\Solicitud::generarRadicado($tipo),
        ]);
        return [$s, $v];
    }

    public function test_rrhh_confirma_salida(): void
    {
        $this->seed();
        [$s, $v] = $this->comisionConViajero();
        $rrhh = \App\Models\Usuario::where('email','rrhh@demo.test')->firstOrFail();

        $this->actingAs($rrhh)->patch(route('viaticos.salida.confirmar', [$s, $v]), ['confirmada' => true])
            ->assertRedirect();
        $this->assertTrue($v->fresh()->salida_confirmada);

        $this->actingAs($rrhh)->patch(route('viaticos.salida.confirmar', [$s, $v]), ['confirmada' => false])
            ->assertRedirect();
        $this->assertFalse($v->fresh()->salida_confirmada);
    }

    public function test_no_rrhh_no_confirma_salida(): void
    {
        $this->seed();
        [$s, $v] = $this->comisionConViajero();
        $otro = \App\Models\Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->actingAs($otro)->patch(route('viaticos.salida.confirmar', [$s, $v]), ['confirmada' => true])
            ->assertForbidden();
    }
}
