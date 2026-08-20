<?php
namespace Tests\Feature;

use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Empleados, Solicitud, SolicitudViaticos, ViajeroComision, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorWorkflowViaticosTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private TipoSolicitud $tipo;
    private Usuario $liderComite;
    private Usuario $contabilidadLider;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->tipo              = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $this->liderComite       = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $this->contador          = Usuario::where('email','contador@demo.test')->firstOrFail();
    }

    private function crearSolicitudViaticos(): Solicitud
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision'    => 'Comité técnico',
            'municipio_destino'  => 'Medellín',
            'observacion'        => 'Capacitación',
        ]);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => Empleados::first()->id,
            'motivo'                => 'Capacitación',
            'fecha_salida'          => now()->addDays(5)->toDateString(),
            'hora_salida'           => '08:00',
            'fecha_regreso'         => now()->addDays(7)->toDateString(),
            'hora_regreso'          => '17:00',
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $this->tipo->id,
            'solicitante_id'    => $this->liderComite->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'borrador',
            'radicado'          => Solicitud::generarRadicado($this->tipo),
        ]);
    }

    public function test_flujo_completo_viaticos(): void
    {
        $solicitud = $this->crearSolicitudViaticos();

        // El solicitante envia la comision directamente al contador.
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        // El contador presenta el informe (liquida).
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
        $this->assertEquals('liquidada', $solicitud->fresh()->estado);

        // El contador la envia al lider de contabilidad.
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_revision', $this->contador);
        $this->assertEquals('revisada', $solicitud->fresh()->estado);

        // El lider de contabilidad la envia a gerencia.
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_gerencia', $this->contabilidadLider);
        $this->assertEquals('en_gerencia', $solicitud->fresh()->estado);

        // En gerencia, el lider de contabilidad aprueba y cierra.
        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contabilidadLider);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
    }

    public function test_rol_incorrecto_rechazado(): void
    {
        $solicitud = $this->crearSolicitudViaticos();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);

        // El lider de comite no puede liquidar; eso es del contador.
        $this->expectException(TransicionNoPermitidaException::class);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->liderComite);
    }

    public function test_devolver_resuelve_la_transicion_del_estado_actual(): void
    {
        // La accion 'devolver' existe desde 'enviada' (->borrador) y desde 'revisada'
        // (->liquidada). El motor debe aplicar la del ESTADO ACTUAL, no la primera del JSON.
        $solicitud = $this->crearSolicitudViaticos();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_revision', $this->contador);
        $this->assertEquals('revisada', $solicitud->fresh()->estado);

        // Devolver desde 'revisada' debe llevar a 'liquidada' (no a 'borrador').
        $this->motor->aplicarTransicion($solicitud->fresh(), 'devolver', $this->contabilidadLider);
        $this->assertEquals('liquidada', $solicitud->fresh()->estado);
    }
}
