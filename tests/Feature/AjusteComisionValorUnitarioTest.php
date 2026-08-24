<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AjusteComisionValorUnitarioTest extends TestCase
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

    public function test_delta_precarga_valor_unitario_de_la_liquidacion_original(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();

        // La comision original tiene gasolina @ 47000 (distinto de la tarifa vigente 50000)
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id,
            'rubro'               => 'gasolina',
            'valor_unitario'      => 47000,
            'dias'                => 2,
        ]);

        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        // Ajuste de fechas: 2 dias -> 3 dias (delta +1 gasolina)
        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'fechas',
            'motivo'              => 'x',
            'estado'              => 'pendiente_liquidacion',
            'fechas_antes'   => ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-11', 'hora_regreso' => '19:00'],
            'fechas_despues' => ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-12', 'hora_regreso' => '19:00'],
        ]);

        $this->actingAs($contador)
            ->get(route('viaticos.ajuste.liquidar', [$solicitud, $ajuste]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Viaticos/LiquidacionAjuste')
                ->where('delta', fn ($delta) => collect($delta)->firstWhere('rubro', 'gasolina')['valor_unitario'] == 47000)
            );
    }

    public function test_rubro_nuevo_cae_a_tarifa_vigente(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();

        // La liquidacion original NO tiene transporte.
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'rubro',
            'motivo'              => 'x',
            'estado'              => 'pendiente_liquidacion',
            'rubro'               => 'transporte',
            'cantidad'            => 1,
        ]);

        // La tarifa vigente de transporte esta sembrada en 0 (el contador la fija).
        $this->actingAs($contador)
            ->get(route('viaticos.ajuste.liquidar', [$solicitud, $ajuste]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Viaticos/LiquidacionAjuste')
                ->where('delta', fn ($delta) => collect($delta)->firstWhere('rubro', 'transporte')['valor_unitario'] == 0)
            );
    }
}
