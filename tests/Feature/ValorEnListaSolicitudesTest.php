<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Http\Resources\SolicitudResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValorEnListaSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(Solicitud $s): array
    {
        // Como lo hace el index: carga las relaciones y resuelve el Resource.
        $s->load(['tipoSolicitud', 'solicitante', 'solicitable']);
        return (new SolicitudResource($s))->resolve();
    }

    public function test_oficina_con_total_a_pagar_muestra_ese_valor(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave', 'OFI')->firstOrFail();

        // Cabecera con el costo estimado en 0 (items sin costo) pero con total_a_pagar asignado.
        $cab = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
            'total' => 0, 'total_a_pagar' => 45000,
        ]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Papel',
            'categoria' => 'producto', 'cantidad' => 2, 'costo_estimado' => null, 'subtotal' => 0,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'pendiente_cierre',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        // La lista debe mostrar el valor real (45000), no el estimado (0).
        $this->assertEquals(45000.0, $this->resolver($s)['total']);
    }

    public function test_oficina_sin_total_a_pagar_muestra_null(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave', 'OFI')->firstOrFail();

        $cab = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 0,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'enviada',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        // Sin valor asignado: null (la UI muestra "—").
        $this->assertNull($this->resolver($s)['total']);
    }

    public function test_viaticos_muestra_su_total(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave', 'VIA')->firstOrFail();

        // Viaticos usa solicitud.total (suma de asignaciones); no tiene total_a_pagar.
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'Medellín', 'observacion' => 'x', 'total' => 215000]);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'x', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id, 'estado' => 'revisada',
            'radicado' => Solicitud::generarRadicado($tipo), 'total' => 215000,
        ]);

        $this->assertEquals(215000.0, $this->resolver($s)['total']);
    }
}
