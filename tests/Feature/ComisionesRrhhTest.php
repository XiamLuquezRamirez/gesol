<?php
namespace Tests\Feature;

use App\Models\{Area, AsignacionViatico, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\ComisionCerradaNotification;
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComisionesRrhhTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderComite;
    private Usuario $contabilidadLider;
    private Usuario $contador;
    private Usuario $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->liderComite       = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $this->contador          = Usuario::where('email', 'contador@demo.test')->firstOrFail();
        $this->rrhh              = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
    }

    private function crearComision(string $salida, string $regreso, string $nombreComision = 'Comité técnico'): Solicitud
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => $nombreComision,
            'municipio_destino' => 'Medellín',
            'observacion'       => 'Capacitación',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => Empleados::first()->id,
            'motivo'                => 'Capacitación',
            'fecha_salida'          => $salida,
            'hora_salida'           => '08:00',
            'fecha_regreso'         => $regreso,
            'hora_regreso'          => '17:00',
            'tipo_pago'             => 'efectivo',
        ]);
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id,
            'rubro'               => TarifaViatico::firstOrFail()->rubro,
            'valor_unitario'      => 15000,
            'dias'                => 2,
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => TipoSolicitud::where('clave', 'VIA')->firstOrFail()->id,
            'solicitante_id'    => $this->liderComite->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'borrador',
            'radicado'          => Solicitud::generarRadicado(TipoSolicitud::where('clave', 'VIA')->firstOrFail()),
        ]);
    }

    /** Deja la comision liquidada (contador presento el informe). */
    private function llevarALiquidada(Solicitud $solicitud): void
    {
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
    }

    /** Deja la comision revisada (lista para que el lider de contabilidad la cierre). */
    private function llevarARevisada(Solicitud $solicitud): void
    {
        $this->llevarALiquidada($solicitud);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_revision', $this->contador);
    }

    public function test_enviar_comision_notifica_a_rrhh(): void
    {
        Notification::fake();
        $solicitud = $this->crearComision('2026-08-10', '2026-08-12');

        // El lider envia la comision al contador: RR. HH. debe enterarse de inmediato.
        $this->actingAs($this->liderComite)
            ->post(route('solicitudes.transicion', $solicitud), ['accion' => 'enviar'])
            ->assertRedirect();

        $this->assertEquals('enviada', $solicitud->fresh()->estado);
        Notification::assertSentTo($this->rrhh, ComisionCerradaNotification::class);
    }

    public function test_cerrar_solicitud_oficina_no_notifica_a_rrhh(): void
    {
        Notification::fake();
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $liderArea = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();

        $cabecera = SolicitudOficina::create([
            'beneficiario' => $liderArea->name, 'urgencia' => 'media', 'justificacion' => 'x',
        ]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $liderArea->id,
            'area_id' => Area::first()->id, 'solicitable_type' => SolicitudOficina::class,
            'solicitable_id' => $cabecera->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($solicitud, 'enviar', $liderArea);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->rrhh);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->contabilidadLider);
        // El pago se registra por abonos (fuera del motor); ese primer abono lleva
        // la solicitud a 'pendiente_cierre'. Aqui simulamos ese estado directamente.
        $solicitud->update(['estado' => 'pendiente_cierre']);

        $this->actingAs($this->contabilidadLider)
            ->post(route('solicitudes.transicion', $solicitud), ['accion' => 'cerrar']);

        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
        // El flujo de oficina puede notificar a RR. HH. por otras razones (p.ej. verificar),
        // pero NUNCA debe enviar el informe de comisión cerrada, que es exclusivo de viáticos.
        Notification::assertNotSentTo($this->rrhh, ComisionCerradaNotification::class);
    }

    public function test_panel_muestra_comisionados_desde_que_se_reportan(): void
    {
        // Una comision ya enviada (reportada a RR. HH.), aun en proceso: debe aparecer.
        $enProceso = $this->crearComision('2026-08-10', '2026-08-12');
        $this->llevarALiquidada($enProceso);
        $this->assertEquals('liquidada', $enProceso->fresh()->estado);

        // Otra en borrador (aun no reportada): NO debe aparecer.
        $borrador = $this->crearComision('2026-08-20', '2026-08-22');
        $this->assertEquals('borrador', $borrador->fresh()->estado);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.comisiones'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Rrhh/Comisiones')
                ->where('comisionados', fn ($data) => count($data) === 1
                    && $data[0]['radicado'] === $enProceso->fresh()->radicado
                    && $data[0]['estado'] === 'liquidada'
                    // Los rubros asignados viajan para el modal "Ver rubros".
                    && count($data[0]['rubros']) === 1
                    && (float) $data[0]['rubros'][0]['subtotal'] === 30000.0
                    && (float) $data[0]['total'] === 30000.0)
            );
    }

    public function test_filtro_por_fecha_incluye_solapamiento_y_excluye_fuera_de_rango(): void
    {
        $enRango = $this->crearComision('2026-08-10', '2026-08-12');
        $this->llevarARevisada($enRango);
        $this->actingAs($this->contabilidadLider)->post(route('solicitudes.transicion', $enRango), ['accion' => 'cerrar']);

        $fueraRango = $this->crearComision('2026-12-01', '2026-12-03');
        $this->llevarARevisada($fueraRango);
        $this->actingAs($this->contabilidadLider)->post(route('solicitudes.transicion', $fueraRango), ['accion' => 'cerrar']);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.comisiones', ['desde' => '2026-08-01', 'hasta' => '2026-08-31']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comisionados', fn ($data) => count($data) === 1
                    && $data[0]['radicado'] === $enRango->fresh()->radicado)
            );
    }

    public function test_filtro_por_comision(): void
    {
        $auditoria = $this->crearComision('2026-08-10', '2026-08-12', 'Auditoría regional');
        $this->llevarARevisada($auditoria);
        $this->actingAs($this->contabilidadLider)->post(route('solicitudes.transicion', $auditoria), ['accion' => 'cerrar']);

        $capacitacion = $this->crearComision('2026-08-15', '2026-08-17', 'Capacitación técnica');
        $this->llevarARevisada($capacitacion);
        $this->actingAs($this->contabilidadLider)->post(route('solicitudes.transicion', $capacitacion), ['accion' => 'cerrar']);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.comisiones', ['comision' => 'Auditoría']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comisionados', fn ($data) => count($data) === 1
                    && $data[0]['comision'] === 'Auditoría regional')
            );
    }

    public function test_no_rrhh_recibe_403(): void
    {
        $this->actingAs($this->contador)
            ->get(route('rrhh.comisiones'))
            ->assertForbidden();
    }

    public function test_rrhh_puede_ver_detalle_de_comision_enviada(): void
    {
        // RR. HH. lista la comision desde que se envia; debe poder abrir su detalle.
        $solicitud = $this->crearComision('2026-08-10', '2026-08-12');
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk();
    }

    public function test_rrhh_puede_ver_detalle_de_comision_liquidada(): void
    {
        $solicitud = $this->crearComision('2026-08-10', '2026-08-12');
        $this->llevarALiquidada($solicitud);
        $this->assertEquals('liquidada', $solicitud->fresh()->estado);

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk();
    }

    public function test_rrhh_no_ve_detalle_de_comision_en_borrador(): void
    {
        // En borrador aun no se ha reportado a RR. HH.: no debe verla.
        $solicitud = $this->crearComision('2026-08-10', '2026-08-12');
        $this->assertEquals('borrador', $solicitud->fresh()->estado);

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.show', $solicitud))
            ->assertForbidden();
    }
}
