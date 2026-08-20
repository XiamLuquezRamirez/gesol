<?php
namespace Tests\Feature;

use App\Models\{AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditarLiquidacionTest extends TestCase
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

    private function crearComisionLiquidada(): array
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => 'Comité técnico',
            'municipio_destino' => 'Medellín',
            'observacion'       => 'Capacitación',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => Empleados::first()->id,
            'motivo'                => 'Capacitación',
            'fecha_salida'          => '2026-08-10',
            'hora_salida'           => '08:00',
            'fecha_regreso'         => '2026-08-12',
            'hora_regreso'          => '17:00',
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

        return [$solicitud->fresh(), $viajero];
    }

    private function payload(ViajeroComision $viajero, float $valor, int $dias): array
    {
        $rubro = TarifaViatico::firstOrFail()->rubro;
        return [
            'asignaciones' => [[
                'viajero_comision_id' => $viajero->id,
                'rubro'               => $rubro,
                'valor_unitario'      => $valor,
                'dias'                => $dias,
            ]],
            'pagos' => [[
                'viajero_comision_id' => $viajero->id,
                'tipo_pago'           => 'efectivo',
            ]],
        ];
    }

    public function test_contador_puede_abrir_liquidacion_estando_liquidada(): void
    {
        [$solicitud] = $this->crearComisionLiquidada();
        $this->assertEquals('liquidada', $solicitud->estado);

        $this->actingAs($this->contador)
            ->get(route('viaticos.liquidacion', $solicitud))
            ->assertOk();
    }

    public function test_contador_puede_corregir_liquidacion_sin_cambiar_estado(): void
    {
        [$solicitud, $viajero] = $this->crearComisionLiquidada();

        $this->actingAs($this->contador)
            ->put(route('viaticos.asignaciones', $solicitud), $this->payload($viajero, 50000, 3))
            ->assertRedirect();

        // Sigue liquidada; no vuelve a disparar la transicion.
        $this->assertEquals('liquidada', $solicitud->fresh()->estado);
        $this->assertDatabaseHas('asignaciones_viaticos', [
            'viajero_comision_id' => $viajero->id,
            'valor_unitario'      => 50000,
            'dias'                => 3,
        ]);
        $this->assertEquals(150000, $solicitud->fresh()->total);
    }

    public function test_no_contador_no_puede_editar_liquidacion(): void
    {
        [$solicitud, $viajero] = $this->crearComisionLiquidada();

        $this->actingAs($this->liderComite)
            ->get(route('viaticos.liquidacion', $solicitud))
            ->assertForbidden();

        $this->actingAs($this->contabilidadLider)
            ->put(route('viaticos.asignaciones', $solicitud), $this->payload($viajero, 50000, 3))
            ->assertForbidden();
    }

    public function test_no_editable_cuando_ya_esta_cerrada(): void
    {
        [$solicitud] = $this->crearComisionLiquidada();
        $this->motor->aplicarTransicion($solicitud, 'enviar_revision', $this->contador);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'enviar_gerencia', $this->contabilidadLider);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contabilidadLider);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);

        $this->actingAs($this->contador)
            ->get(route('viaticos.liquidacion', $solicitud))
            ->assertForbidden();
    }
}
