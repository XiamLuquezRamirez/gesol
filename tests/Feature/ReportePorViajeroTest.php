<?php
namespace Tests\Feature;

use App\Models\{ArchivoViajero, AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportePorViajeroTest extends TestCase
{
    use RefreshDatabase;

    private function rol(string $rol): Usuario
    {
        $u = Usuario::factory()->create();
        $u->assignRole($rol);
        return $u;
    }

    /**
     * Comision de viaticos con un viajero (empleado dado, o el primero) y sus rubros,
     * en fechas dadas. Devuelve [solicitud, viajero].
     */
    private function comisionConRubros(string $salida, string $regreso, array $rubros, string $estado = 'cerrada', ?int $empleadoId = null): array
    {
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab  = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'X', 'observacion' => 'x']);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => $empleadoId ?? Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => $salida, 'hora_salida' => '08:00',
            'fecha_regreso' => $regreso, 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        foreach ($rubros as $rubro => $valor) {
            AsignacionViatico::create([
                'viajero_comision_id' => $viajero->id, 'rubro' => $rubro, 'valor_unitario' => $valor, 'dias' => 1,
            ]);
        }
        $sol = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => Usuario::first()->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        return [$sol, $viajero];
    }

    public function test_agrupa_por_empleado_y_rubro(): void
    {
        $this->seed();
        [$sol, $viajero] = $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 50000, 'cena' => 20000]);
        ArchivoViajero::create([
            'viajero_comision_id' => $viajero->id, 'tipo' => 'comprobante',
            'path' => 'x/y.pdf', 'nombre' => 'pago.pdf', 'usuario_id' => Usuario::first()->id,
        ]);
        // Fuera del rango: no cuenta.
        $this->comisionConRubros('2026-02-10', '2026-02-10', ['gasolina' => 99999]);

        $this->actingAs($this->rol('contador'))
            ->get(route('reportes.por-viajero', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/PorViajero')
                ->where('reporte.total', 70000)
                ->where('reporte.num_empleados', 1)
                ->where('reporte.num_rubros', 2)
                ->where('reporte.viajeros.0.total', 70000)
                // Rubros ordenados por total desc: gasolina (50000) primero.
                ->where('reporte.viajeros.0.rubros.0.rubro', 'gasolina')
                ->where('reporte.viajeros.0.rubros.0.total', 50000)
                ->where('reporte.viajeros.0.rubros.1.rubro', 'cena')
                ->where('reporte.viajeros.0.rubros.1.total', 20000)
                ->where('reporte.viajeros.0.rubros.0.comisiones.0.comprobantes.0.nombre', 'pago.pdf'));
    }

    public function test_filtro_por_empleado(): void
    {
        $this->seed();
        $empleados = Empleados::take(2)->get();
        $this->assertCount(2, $empleados, 'El seed debe tener al menos dos empleados.');
        $a = $empleados[0];
        $b = $empleados[1];

        $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 30000], 'cerrada', $a->id);
        $this->comisionConRubros('2026-01-11', '2026-01-11', ['cena' => 40000], 'cerrada', $b->id);

        $this->actingAs($this->rol('contador'))
            ->get(route('reportes.por-viajero', ['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'empleado' => $a->id]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/PorViajero')
                ->where('filtros.empleado', $a->id)
                ->where('reporte.num_empleados', 1)
                ->where('reporte.total', 30000)
                ->where('reporte.viajeros.0.empleado_id', $a->id));
    }

    public function test_excluye_borrador(): void
    {
        $this->seed();
        // Activa: cuenta.
        $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 30000], 'cerrada');
        // Borrador: no cuenta ni suma.
        $this->comisionConRubros('2026-01-11', '2026-01-11', ['gasolina' => 99999], 'borrador');

        $this->actingAs($this->rol('contador'))
            ->get(route('reportes.por-viajero', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/PorViajero')
                ->where('reporte.total', 30000)
                ->where('reporte.num_empleados', 1));
    }

    public function test_export_pdf_por_empleado(): void
    {
        $this->seed();
        [$sol, $viajero] = $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 50000], 'cerrada');
        $empleadoId = $viajero->empleado_id;

        $res = $this->actingAs($this->rol('contador'))
            ->get(route('reportes.por-viajero', ['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'empleado' => $empleadoId, 'export' => 'pdf']));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', $res->headers->get('content-disposition'));
    }

    public function test_export_xlsx_por_empleado(): void
    {
        $this->seed();
        [$sol, $viajero] = $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 50000], 'cerrada');
        $empleadoId = $viajero->empleado_id;

        $res = $this->actingAs($this->rol('contador'))
            ->get(route('reportes.por-viajero', ['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'empleado' => $empleadoId, 'export' => 'xlsx']));

        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', $res->headers->get('content-disposition'));
    }
}
