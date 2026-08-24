<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AjusteComisionFlujoTest extends TestCase
{
    use RefreshDatabase;

    /** Crea una comision VIA cerrada con un viajero. Devuelve [$solicitud, $viajero, $lider]. */
    private function comisionCerrada(): array
    {
        $tipo  = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $lider = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();

        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => 'Comision cerrada',
            'municipio_destino' => 'X',
            'observacion'       => 'x',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => Empleados::first()->id,
            'motivo'                => 'm',
            'fecha_salida'          => '2026-01-10',
            'hora_salida'           => '08:00',
            'fecha_regreso'         => '2026-01-10',
            'hora_regreso'          => '15:00',
        ]);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id,
            'solicitante_id'    => $lider->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'cerrada',
            'radicado'          => Solicitud::generarRadicado($tipo),
        ]);

        return [$solicitud, $viajero, $lider];
    }

    public function test_lider_solicita_ajuste_fechas_crea_pendiente_y_notifica_contador(): void
    {
        Notification::fake();
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        $this->actingAs($lider)->put(route('viaticos.ajustar', $solicitud), [
            'motivo'   => 'Se extendio la comision',
            'viajeros' => [[
                'viajero_comision_id' => $viajero->id,
                'fecha_salida'  => $viajero->fecha_salida->toDateString(),
                'hora_salida'   => $viajero->hora_salida,
                'fecha_regreso' => $viajero->fecha_regreso->addDay()->toDateString(),
                'hora_regreso'  => '19:00',
            ]],
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertSame('cerrada', $solicitud->estado, 'La comision sigue cerrada');

        $ajuste = AjusteComision::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($ajuste);
        $this->assertSame('pendiente_liquidacion', $ajuste->estado);
        $this->assertSame('fechas', $ajuste->tipo);
        $this->assertNotNull($ajuste->fechas_antes);
        $this->assertNotNull($ajuste->fechas_despues);

        // El anexo NO muta la comision cerrada: la fecha real del viajero no cambia.
        $this->assertSame('2026-01-10', $viajero->fresh()->fecha_regreso->toDateString());

        Notification::assertSentTo($contador, AvisoTransicionNotification::class);
    }

    public function test_lider_solicita_ajuste_rubro_cerrada_crea_pendiente(): void
    {
        Notification::fake();
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();

        $this->actingAs($lider)->post(route('viaticos.reajustar-rubro', $solicitud), [
            'viajero_comision_id' => $viajero->id,
            'rubro'               => 'gasolina',
            'cantidad'            => 1,
            'motivo'              => 'Falto gasolina',
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertSame('cerrada', $solicitud->estado);

        $ajuste = AjusteComision::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($ajuste);
        $this->assertSame('rubro', $ajuste->tipo);
        $this->assertSame('pendiente_liquidacion', $ajuste->estado);
        $this->assertSame('gasolina', $ajuste->rubro);
        $this->assertSame(1, $ajuste->cantidad);
    }
}
