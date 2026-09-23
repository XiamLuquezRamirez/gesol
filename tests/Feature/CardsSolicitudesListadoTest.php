<?php
namespace Tests\Feature;

use App\Models\{
    AsignacionViatico, Contrato, Empleados, ItemObra, Solicitud, SolicitudObra,
    SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Verifica que las cards del listado de solicitudes (SolicitudResource) traigan
 * la informacion enriquecida por tipo, para identificar cada solicitud sin entrar.
 */
class CardsSolicitudesListadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_viaticos_trae_viajeros_comision_y_fechas(): void
    {
        $this->seed();
        $solicitante = Usuario::first();
        $empleado    = Empleados::first();
        $contrato    = Contrato::first();

        $cab = SolicitudViaticos::create([
            'nombre_comision' => 'Supervision de obra',
            'municipio_destino' => 'X', 'observacion' => 'x',
        ]);
        $cab->municipios()->attach(\App\Models\Municipio::first()->id);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => $empleado->id,
            'contrato_id' => $contrato?->id,
            'motivo' => 'm', 'fecha_salida' => '2026-03-05', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-03-08', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id, 'rubro' => 'gasolina', 'valor_unitario' => 50000, 'dias' => 1,
        ]);
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $solicitante->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => 'enviada', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        $nombreViajero = $viajero->nombreMostrado;

        $this->actingAs($solicitante)
            ->get(route('solicitudes.index', ['tab' => 'mias']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('solicitudes.data', 1)
                ->where('solicitudes.data.0.viaticos.nombre_comision', 'Supervision de obra')
                ->where('solicitudes.data.0.viaticos.fecha_salida', '2026-03-05')
                ->where('solicitudes.data.0.viaticos.fecha_regreso', '2026-03-08')
                ->where('solicitudes.data.0.viaticos.num_viajeros', 1)
                ->where('solicitudes.data.0.viaticos.viajeros.0', $nombreViajero));
    }

    public function test_card_oficina_trae_justificacion(): void
    {
        $this->seed();
        $solicitante = Usuario::first();

        $cab = SolicitudOficina::create([
            'beneficiario' => 'B', 'urgencia' => 'media',
            'justificacion' => 'Compra de tinta para impresora',
        ]);
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $solicitante->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id,
            'estado' => 'enviada', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        $this->actingAs($solicitante)
            ->get(route('solicitudes.index', ['tab' => 'mias']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('solicitudes.data', 1)
                ->where('solicitudes.data.0.oficina.justificacion', 'Compra de tinta para impresora'));
    }

    public function test_card_obra_trae_observacion_contrato_y_fecha_entrega(): void
    {
        $this->seed();
        $solicitante = Usuario::first();
        $contrato    = Contrato::first();

        $cab = SolicitudObra::create([
            'nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21',
            'fecha_entrega' => '2026-10-01', 'contrato_id' => $contrato?->id,
            'observacion' => 'Materiales para la placa',
        ]);
        ItemObra::create(['solicitud_obra_id' => $cab->id, 'especificacion' => 'CEMENTO', 'unidad' => 'BOLSA', 'cantidad' => 10]);
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $solicitante->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'enviada', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        $this->actingAs($solicitante)
            ->get(route('solicitudes.index', ['tab' => 'mias']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('solicitudes.data', 1)
                ->where('solicitudes.data.0.obra.observacion', 'Materiales para la placa')
                ->where('solicitudes.data.0.obra.fecha_entrega', '2026-10-01'));
    }
}
