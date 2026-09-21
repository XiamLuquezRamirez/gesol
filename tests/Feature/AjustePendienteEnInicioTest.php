<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Un reajuste (AjusteComision) sobre una comision cerrada debe contarse y
 * listarse como "pendiente por aprobar" para el rol que debe actuar sobre el:
 *   - contador  -> ajuste en 'pendiente_liquidacion' o 'devuelto' (debe liquidar)
 *   - contabilidad_lider -> ajuste en 'liquidado' (debe aprobar)
 * La comision sigue 'cerrada', asi que el reajuste NO es una transicion del motor:
 * este test cubre que igual aparece en el inicio y en el tab de pendientes.
 */
class AjustePendienteEnInicioTest extends TestCase
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
            'fecha_salida'          => '2026-01-10', 'hora_salida'  => '08:00',
            'fecha_regreso'         => '2026-01-10', 'hora_regreso' => '15:00',
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

    private function crearAjuste(Solicitud $solicitud, ViajeroComision $viajero, Usuario $lider, string $estado): AjusteComision
    {
        return AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'rubro',
            'motivo'              => 'x',
            'estado'              => $estado,
            'rubro'               => 'gasolina',
            'cantidad'            => 1,
            'total_delta'         => 50000,
        ]);
    }

    public function test_inicio_cuenta_ajuste_pendiente_para_contador(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        $this->crearAjuste($solicitud, $viajero, $lider, 'pendiente_liquidacion');

        $this->actingAs($contador)
            ->get(route('inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Inicio/Index')
                ->where('stats.pendientes', 1));
    }

    public function test_inicio_cuenta_ajuste_liquidado_para_lider_contabilidad(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $lcontab = Usuario::factory()->create();
        $lcontab->assignRole('contabilidad_lider');

        $this->crearAjuste($solicitud, $viajero, $lider, 'liquidado');

        $this->actingAs($lcontab)
            ->get(route('inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Inicio/Index')
                ->where('stats.pendientes', 1));
    }

    public function test_inicio_no_cuenta_ajuste_liquidado_para_contador(): void
    {
        // El ajuste 'liquidado' es tarea del lider de contabilidad, no del contador.
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        $this->crearAjuste($solicitud, $viajero, $lider, 'liquidado');

        $this->actingAs($contador)
            ->get(route('inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Inicio/Index')
                ->where('stats.pendientes', 0));
    }

    public function test_tab_pendientes_lista_la_comision_con_ajuste_pendiente(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');

        $this->crearAjuste($solicitud, $viajero, $lider, 'devuelto');

        $this->actingAs($contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Solicitudes/Index')
                ->has('solicitudes.data', 1)
                ->where('solicitudes.data.0.id', $solicitud->id)
                ->where('solicitudes.data.0.ajuste_pendiente', true));
    }

    public function test_ajuste_aprobado_ya_no_es_pendiente(): void
    {
        // Un ajuste ya aprobado no debe seguir contando como pendiente.
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create();
        $contador->assignRole('contador');
        $lcontab = Usuario::factory()->create();
        $lcontab->assignRole('contabilidad_lider');

        $this->crearAjuste($solicitud, $viajero, $lider, 'aprobado');

        $this->actingAs($contador)
            ->get(route('inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stats.pendientes', 0));

        $this->actingAs($lcontab)
            ->get(route('inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stats.pendientes', 0));
    }
}
