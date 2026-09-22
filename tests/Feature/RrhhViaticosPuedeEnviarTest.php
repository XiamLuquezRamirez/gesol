<?php
namespace Tests\Feature;

use App\Models\{Empleados, Municipio, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RR. HH. puede crear y enviar comisiones de viaticos (ademas de los lideres).
 */
class RrhhViaticosPuedeEnviarTest extends TestCase
{
    use RefreshDatabase;

    public function test_rrhh_puede_crear_comision_de_viaticos(): void
    {
        $this->seed();
        $rrhh = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();

        $this->actingAs($rrhh)->post(route('viaticos.store'), [
            'nombre_comision' => 'Comision RRHH',
            'municipios' => Municipio::take(1)->pluck('id')->all(),
            'observacion' => 'x',
            'viajeros' => [[
                'empleado_id' => Empleados::first()->id, 'es_externo' => false,
                'motivo' => 'Gestion', 'fecha_salida' => '2026-09-10', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-09-11', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('solicitudes_viaticos', ['nombre_comision' => 'Comision RRHH']);
    }

    public function test_rrhh_puede_enviar_comision_de_viaticos(): void
    {
        $this->seed();
        $rrhh = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab  = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'X', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-09-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-09-11', 'hora_regreso' => '17:00',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $rrhh->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        // RR. HH. tiene la accion 'enviar' disponible y puede aplicarla.
        $motor = app(MotorWorkflow::class);
        $this->assertTrue($motor->puede($s, 'enviar', $rrhh));

        $this->actingAs($rrhh)->post(route('solicitudes.transicion', $s), ['accion' => 'enviar'])
            ->assertRedirect();
        $this->assertSame('enviada', $s->fresh()->estado);
    }
}
