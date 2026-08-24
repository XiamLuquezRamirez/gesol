<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
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

    public function test_contador_liquida_ajuste_calcula_delta_y_pasa_a_liquidado(): void
    {
        Notification::fake();
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');
        $lcontab = Usuario::factory()->create();
        $lcontab->assignRole('contabilidad_lider');

        // Ajuste de fechas: 1 dia (10@08:00-15:00) -> 2 dias (10@08:00 al 11@19:00)
        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'fechas',
            'motivo'              => 'x',
            'estado'              => 'pendiente_liquidacion',
            'fechas_antes'   => ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-10', 'hora_regreso' => '15:00'],
            'fechas_despues' => ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-11', 'hora_regreso' => '19:00'],
        ]);

        // GET pantalla: debe traer el delta propuesto
        $this->actingAs($contador)
            ->get(route('viaticos.ajuste.liquidar', [$solicitud, $ajuste]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Viaticos/LiquidacionAjuste')->has('delta'));

        // PUT: persistir asignaciones del anexo
        $this->actingAs($contador)->put(route('viaticos.ajuste.asignaciones', [$solicitud, $ajuste]), [
            'asignaciones' => [
                ['rubro' => 'gasolina', 'valor_unitario' => 50000, 'dias' => 1],
                ['rubro' => 'cena', 'valor_unitario' => 20000, 'dias' => 1],
            ],
        ])->assertRedirect();

        $ajuste->refresh();
        $this->assertSame('liquidado', $ajuste->estado);
        $this->assertEquals(70000, $ajuste->total_delta);
        $this->assertNotNull($ajuste->liquidado_por);
        Notification::assertSentTo($lcontab, AvisoTransicionNotification::class);

        // La comision cerrada no cambia estado
        $solicitud->refresh();
        $this->assertSame('cerrada', $solicitud->estado);
    }

    public function test_lider_contabilidad_aprueba_ajuste(): void
    {
        Notification::fake();
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $lcontab = Usuario::factory()->create();
        $lcontab->assignRole('contabilidad_lider');

        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'rubro',
            'motivo'              => 'x',
            'estado'              => 'liquidado',
            'rubro'               => 'gasolina',
            'cantidad'            => 1,
            'total_delta'         => 50000,
        ]);

        $this->actingAs($lcontab)
            ->post(route('viaticos.ajuste.aprobar', [$solicitud, $ajuste]))
            ->assertRedirect();

        $ajuste->refresh();
        $this->assertSame('aprobado', $ajuste->estado);
        $this->assertNotNull($ajuste->aprobado_por);
        $this->assertNotNull($ajuste->aprobado_en);
    }

    public function test_lider_contabilidad_devuelve_ajuste_para_recalcular(): void
    {
        Notification::fake();
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');
        $lcontab = Usuario::factory()->create();
        $lcontab->assignRole('contabilidad_lider');

        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'rubro',
            'motivo'              => 'x',
            'estado'              => 'liquidado',
            'rubro'               => 'gasolina',
            'cantidad'            => 1,
        ]);

        $this->actingAs($lcontab)
            ->post(route('viaticos.ajuste.devolver', [$solicitud, $ajuste]), [
                'motivo_devolucion' => 'Revisar el valor',
            ])
            ->assertRedirect();

        $ajuste->refresh();
        $this->assertSame('devuelto', $ajuste->estado);
        $this->assertSame('Revisar el valor', $ajuste->motivo_devolucion);

        Notification::assertSentTo($contador, AvisoTransicionNotification::class);
    }

    public function test_contador_no_puede_aprobar(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'rubro',
            'motivo'              => 'x',
            'estado'              => 'liquidado',
            'rubro'               => 'gasolina',
            'cantidad'            => 1,
        ]);

        $this->actingAs($contador)
            ->post(route('viaticos.ajuste.aprobar', [$solicitud, $ajuste]))
            ->assertForbidden();
    }

    public function test_no_aprueba_ajuste_no_liquidado(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $lcontab = Usuario::factory()->create();
        $lcontab->assignRole('contabilidad_lider');

        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'rubro',
            'motivo'              => 'x',
            'estado'              => 'pendiente_liquidacion',
            'rubro'               => 'gasolina',
            'cantidad'            => 1,
        ]);

        $this->actingAs($lcontab)
            ->post(route('viaticos.ajuste.aprobar', [$solicitud, $ajuste]))
            ->assertForbidden();
    }
}
