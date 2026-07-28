<?php
namespace Tests\Feature;

use App\Models\{AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VerRubrosDetalleTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderComite;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor       = app(MotorWorkflow::class);
        $this->liderComite = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->contador    = Usuario::where('email', 'contador@demo.test')->firstOrFail();
    }

    public function test_el_detalle_incluye_los_rubros_de_cada_viajero(): void
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision' => 'Comité técnico', 'municipio_destino' => 'Medellín', 'observacion' => 'x',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'Capacitación', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id, 'rubro' => TarifaViatico::firstOrFail()->rubro,
            'valor_unitario' => 15000, 'dias' => 2,
        ]);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => TipoSolicitud::where('clave', 'VIA')->firstOrFail()->id,
            'solicitante_id' => $this->liderComite->id, 'solicitable_type' => SolicitudViaticos::class,
            'solicitable_id' => $cabecera->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado(TipoSolicitud::where('clave', 'VIA')->firstOrFail()),
        ]);
        // El contador (que participa en el flujo) puede ver el detalle.
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);

        $this->actingAs($this->contador)
            ->get(route('solicitudes.show', $solicitud))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Solicitudes/Detalle')
                ->where('solicitud.solicitable.viajeros', fn ($viajeros) => count($viajeros) === 1
                    && count($viajeros[0]['asignaciones']) === 1
                    && (float) $viajeros[0]['asignaciones'][0]['subtotal'] === 30000.0)
            );
    }
}
