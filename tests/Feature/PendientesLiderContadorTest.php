<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendientesLiderContadorTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderArea;
    private Usuario $liderComite;
    private Usuario $rrhh;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor       = app(MotorWorkflow::class);
        $this->liderArea   = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->liderComite = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->rrhh        = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $this->contador    = Usuario::where('email', 'contador@demo.test')->firstOrFail();
    }

    /** Crea una solicitud de oficina en estado 'verificada'. */
    private function oficinaVerificada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x']);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh);
        return $s->fresh();
    }

    /** Crea una comision de viaticos en estado 'revisada'. */
    private function viaticosRevisada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab  = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'Medellín', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'x', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderComite->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($s->fresh(), 'liquidar', $this->contador);
        $this->motor->aplicarTransicion($s->fresh(), 'enviar_revision', $this->contador);
        return $s->fresh();
    }

    public function test_contador_puede_ver_detalle_de_oficina_verificada(): void
    {
        $s = $this->oficinaVerificada();
        $this->assertEquals('verificada', $s->estado);
        $this->assertTrue($this->contador->can('verDetalle', $s));

        $this->actingAs($this->contador)->get(route('solicitudes.show', $s))->assertOk();
    }

    public function test_contador_puede_ver_detalle_de_viaticos_revisada(): void
    {
        $s = $this->viaticosRevisada();
        $this->assertEquals('revisada', $s->estado);
        $this->assertTrue($this->contador->can('verDetalle', $s));
    }

    public function test_contador_no_gana_acciones_sobre_oficina_verificada(): void
    {
        $s = $this->oficinaVerificada();
        // Solo lectura: el motor no ofrece transiciones al contador en ese estado.
        $this->assertEmpty($this->motor->accionesDisponibles($s, $this->contador));
    }
}
