<?php
namespace Tests\Feature;

use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Solicitud, SolicitudViaticos, ViajeroComision, TipoSolicitud, Usuario};
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
            'motivo'             => 'Capacitación',
            'fecha_salida'       => now()->addDays(5)->toDateString(),
            'fecha_regreso'      => now()->addDays(7)->toDateString(),
        ]);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'usuario_id'            => $this->liderComite->id,
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

        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->contabilidadLider);
        $this->assertEquals('aprobada_monto', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
        $this->assertEquals('liquidada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contador);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
    }

    public function test_rol_incorrecto_rechazado(): void
    {
        $solicitud = $this->crearSolicitudViaticos();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);

        $this->expectException(TransicionNoPermitidaException::class);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->liderComite);
    }
}
