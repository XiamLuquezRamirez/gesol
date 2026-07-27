<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
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

    private function crearComision(string $salida, string $regreso): Solicitud
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => 'Comité técnico',
            'municipio_destino' => 'Medellín',
            'observacion'       => 'Capacitación',
        ]);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => Empleados::first()->id,
            'motivo'                => 'Capacitación',
            'fecha_salida'          => $salida,
            'hora_salida'           => '08:00',
            'fecha_regreso'         => $regreso,
            'hora_regreso'          => '17:00',
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

    private function llevarACerrada(Solicitud $solicitud): void
    {
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->contabilidadLider);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
    }

    public function test_cerrar_comision_notifica_a_rrhh(): void
    {
        Notification::fake();
        $solicitud = $this->crearComision('2026-08-10', '2026-08-12');
        $this->llevarACerrada($solicitud);

        // El cierre pasa por la ruta de transicion (flujo generico), como el contador.
        $this->actingAs($this->contador)
            ->post(route('solicitudes.transicion', $solicitud), ['accion' => 'cerrar'])
            ->assertRedirect();

        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
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
        $this->motor->aplicarTransicion($solicitud->fresh(), 'pagar', $this->contabilidadLider);

        $this->actingAs($this->contabilidadLider)
            ->post(route('solicitudes.transicion', $solicitud), ['accion' => 'cerrar']);

        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
        // El flujo de oficina puede notificar a RR. HH. por otras razones (p.ej. verificar),
        // pero NUNCA debe enviar el informe de comisión cerrada, que es exclusivo de viáticos.
        Notification::assertNotSentTo($this->rrhh, ComisionCerradaNotification::class);
    }

    public function test_panel_muestra_comisionados_de_comisiones_cerradas(): void
    {
        $cerrada = $this->crearComision('2026-08-10', '2026-08-12');
        $this->llevarACerrada($cerrada);
        $this->actingAs($this->contador)->post(route('solicitudes.transicion', $cerrada), ['accion' => 'cerrar']);

        // Otra comision solo liquidada (no cerrada): no debe aparecer.
        $liquidada = $this->crearComision('2026-08-20', '2026-08-22');
        $this->llevarACerrada($liquidada);
        $this->assertEquals('liquidada', $liquidada->fresh()->estado);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.comisiones'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Rrhh/Comisiones')
                ->where('comisionados', fn ($data) => count($data) === 1
                    && $data[0]['radicado'] === $cerrada->fresh()->radicado)
            );
    }

    public function test_filtro_por_fecha_incluye_solapamiento_y_excluye_fuera_de_rango(): void
    {
        $enRango = $this->crearComision('2026-08-10', '2026-08-12');
        $this->llevarACerrada($enRango);
        $this->actingAs($this->contador)->post(route('solicitudes.transicion', $enRango), ['accion' => 'cerrar']);

        $fueraRango = $this->crearComision('2026-12-01', '2026-12-03');
        $this->llevarACerrada($fueraRango);
        $this->actingAs($this->contador)->post(route('solicitudes.transicion', $fueraRango), ['accion' => 'cerrar']);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.comisiones', ['desde' => '2026-08-01', 'hasta' => '2026-08-31']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comisionados', fn ($data) => count($data) === 1
                    && $data[0]['radicado'] === $enRango->fresh()->radicado)
            );
    }

    public function test_no_rrhh_recibe_403(): void
    {
        $this->actingAs($this->contador)
            ->get(route('rrhh.comisiones'))
            ->assertForbidden();
    }
}
