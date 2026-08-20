<?php
namespace Tests\Feature;

use App\Mail\LiquidacionViajeroMail;
use App\Models\{AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LiquidacionPdfTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderComite;
    private Usuario $contabilidadLider;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->liderComite       = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $this->contador          = Usuario::where('email', 'contador@demo.test')->firstOrFail();
    }

    private function crearComisionCerrada(bool $empleadoConCorreo = true): array
    {
        $empleado = Empleados::first();
        if (!$empleadoConCorreo) {
            $empleado->update(['email' => null]);
        }

        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => 'Comité técnico',
            'municipio_destino' => 'La Jagua de Ibirico',
            'observacion'       => 'Capacitación',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => $empleado->id,
            'motivo'                => 'Capacitación',
            'fecha_salida'          => '2026-07-23',
            'hora_salida'           => '08:00',
            'fecha_regreso'         => '2026-07-24',
            'hora_regreso'          => '17:00',
            'tipo_pago'             => 'efectivo',
        ]);
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id,
            'rubro'               => TarifaViatico::firstOrFail()->rubro,
            'valor_unitario'      => 15000,
            'dias'                => 1,
        ]);

        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => TipoSolicitud::where('clave', 'VIA')->firstOrFail()->id,
            'solicitante_id'    => $this->liderComite->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'borrador',
            'radicado'          => Solicitud::generarRadicado(TipoSolicitud::where('clave', 'VIA')->firstOrFail()),
        ]);
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_revision', $this->contador);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_gerencia', $this->contabilidadLider);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contabilidadLider);

        return [$solicitud->fresh(), $viajero];
    }

    public function test_descarga_pdf_de_comision_cerrada(): void
    {
        [$solicitud, $viajero] = $this->crearComisionCerrada();

        $resp = $this->actingAs($this->contador)
            ->get(route('liquidacion.pdf', [$solicitud, $viajero]));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');
    }

    public function test_no_descarga_si_no_esta_cerrada(): void
    {
        // Comision solo liquidada (no cerrada).
        $empleado = Empleados::first();
        $cabecera = SolicitudViaticos::create([
            'nombre_comision' => 'X', 'municipio_destino' => 'Y', 'observacion' => 'z',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id, 'empleado_id' => $empleado->id,
            'motivo' => 'm', 'fecha_salida' => '2026-07-23', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-07-24', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => TipoSolicitud::where('clave', 'VIA')->firstOrFail()->id,
            'solicitante_id' => $this->liderComite->id, 'solicitable_type' => SolicitudViaticos::class,
            'solicitable_id' => $cabecera->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado(TipoSolicitud::where('clave', 'VIA')->firstOrFail()),
        ]);
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);

        $this->actingAs($this->contador)
            ->get(route('liquidacion.pdf', [$solicitud->fresh(), $viajero]))
            ->assertForbidden();
    }

    public function test_enviar_correo_al_empleado(): void
    {
        Mail::fake();
        [$solicitud, $viajero] = $this->crearComisionCerrada();

        $this->actingAs($this->contador)
            ->post(route('liquidacion.correo', [$solicitud, $viajero]))
            ->assertRedirect();

        Mail::assertSent(LiquidacionViajeroMail::class, fn ($m) => $m->hasTo(Empleados::first()->email));
    }

    public function test_no_envia_si_empleado_sin_correo(): void
    {
        Mail::fake();
        [$solicitud, $viajero] = $this->crearComisionCerrada(empleadoConCorreo: false);

        $this->actingAs($this->contador)
            ->post(route('liquidacion.correo', [$solicitud, $viajero]))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }
}
