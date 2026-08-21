<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AjustarComisionTest extends TestCase
{
    use RefreshDatabase;

    private function comisionConViajero(string $estado = 'liquidada'): array
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        return [$s, $v, $lider];
    }

    public function test_lider_ajusta_fechas_y_notifica(): void
    {
        Notification::fake();
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('liquidada');

        $this->actingAs($lider)->put(route('viaticos.ajustar', $s), [
            'motivo' => 'Se queda 2 dias mas',
            'viajeros' => [[
                'viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $this->assertEquals('2026-08-24', $v->fresh()->fecha_regreso->toDateString());
        $this->assertDatabaseHas('transiciones_solicitud', ['solicitud_id' => $s->id, 'accion' => 'ajustar']);
        $contador = Usuario::where('email','contador@demo.test')->firstOrFail();
        Notification::assertSentTo($contador, AvisoTransicionNotification::class);
    }

    public function test_no_solicitante_no_ajusta(): void
    {
        $this->seed();
        [$s, $v] = $this->comisionConViajero('liquidada');
        $otro = Usuario::where('email','contador@demo.test')->firstOrFail();
        $this->actingAs($otro)->put(route('viaticos.ajustar', $s), [
            'motivo' => 'x', 'viajeros' => [['viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00']],
        ])->assertForbidden();
    }

    public function test_no_ajusta_cerrada(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('cerrada');
        $this->actingAs($lider)->put(route('viaticos.ajustar', $s), [
            'motivo' => 'x', 'viajeros' => [['viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00']],
        ])->assertForbidden();
    }

    public function test_ajuste_sin_motivo_es_invalido(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('liquidada');
        $this->actingAs($lider)->from(route('solicitudes.show', $s))->put(route('viaticos.ajustar', $s), [
            'viajeros' => [['viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00']],
        ])->assertSessionHasErrors('motivo');
    }
}
