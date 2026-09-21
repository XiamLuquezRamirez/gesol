<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, AjusteComision, Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Models\{ArchivoViajero, AsignacionViatico};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private function rol(string $rol): Usuario
    {
        $u = Usuario::factory()->create();
        $u->assignRole($rol);
        return $u;
    }

    /** Comision de viaticos con un viajero y sus rubros, en fechas dadas. Devuelve [solicitud, viajero]. */
    private function comisionConRubros(string $salida, string $regreso, array $rubros, string $estado = 'cerrada'): array
    {
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab  = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'X', 'observacion' => 'x']);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
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

    public function test_admin_contador_y_contabilidad_ven_el_panel(): void
    {
        $this->seed();
        foreach (['admin', 'contador', 'contabilidad_lider'] as $rol) {
            $this->actingAs($this->rol($rol))
                ->get(route('reportes.index'))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p->component('Reportes/Panel'));
        }
    }

    public function test_rrhh_y_lider_area_no_acceden(): void
    {
        $this->seed();
        foreach (['rrhh', 'lider_area'] as $rol) {
            $this->actingAs($this->rol($rol))
                ->get(route('reportes.index'))->assertForbidden();
            $this->actingAs($this->rol($rol))
                ->get(route('reportes.viaticos'))->assertForbidden();
        }
    }

    public function test_viaticos_detalla_comisiones_con_comprobantes_y_total(): void
    {
        $this->seed();
        [$sol, $viajero] = $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 50000, 'cena' => 20000]);
        // Un comprobante de pago para el viajero.
        ArchivoViajero::create([
            'viajero_comision_id' => $viajero->id, 'tipo' => 'comprobante',
            'path' => 'x/y.pdf', 'nombre' => 'pago.pdf', 'usuario_id' => Usuario::first()->id,
        ]);
        // Fuera del rango: no cuenta.
        $this->comisionConRubros('2026-02-10', '2026-02-10', ['gasolina' => 99999]);

        $this->actingAs($this->rol('contador'))
            ->get(route('reportes.viaticos', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/Viaticos')
                ->where('reporte.total', 70000)
                ->where('reporte.num_comisiones', 1)
                ->where('reporte.comisiones.0.total', 70000)
                ->where('reporte.comisiones.0.viajeros.0.comprobantes.0.nombre', 'pago.pdf'));
    }

    public function test_oficina_suma_abonos_del_rango(): void
    {
        $this->seed();
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 100000, 'total_a_pagar' => 100000]);
        ItemOficina::create(['solicitud_oficina_id' => $cab->id, 'nombre' => 'x', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 100000, 'subtotal' => 100000]);
        Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => Usuario::first()->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id,
            'estado' => 'pendiente_cierre', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        AbonoOficina::create(['solicitud_oficina_id' => $cab->id, 'monto' => 40000, 'fecha_pago' => '2026-01-15', 'soporte_path' => 'x', 'soporte_nombre' => 'x', 'usuario_id' => Usuario::first()->id]);
        AbonoOficina::create(['solicitud_oficina_id' => $cab->id, 'monto' => 10000, 'fecha_pago' => '2026-03-15', 'soporte_path' => 'y', 'soporte_nombre' => 'y', 'usuario_id' => Usuario::first()->id]);

        $this->actingAs($this->rol('contabilidad_lider'))
            ->get(route('reportes.oficina', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/Oficina')
                ->where('reporte.total_pagado', 40000)
                ->where('reporte.num_solicitudes', 1));
    }

    public function test_personal_cuenta_dias_de_comision(): void
    {
        $this->seed();
        $this->comisionConRubros('2026-01-10', '2026-01-12', ['gasolina' => 1000]); // 3 dias

        $this->actingAs($this->rol('admin'))
            ->get(route('reportes.personal', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/Personal')
                ->where('reporte.num_empleados', 1)
                ->where('reporte.total_dias', 3));
    }

    public function test_comprobantes_pendientes_lista_comisiones_sin_comprobante(): void
    {
        $this->seed();
        // Cerrada sin comprobante -> debe aparecer.
        $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 1000], 'cerrada');
        // Cerrada CON comprobante -> no aparece.
        [$sol2, $v2] = $this->comisionConRubros('2026-01-11', '2026-01-11', ['gasolina' => 1000], 'cerrada');
        ArchivoViajero::create(['viajero_comision_id' => $v2->id, 'tipo' => 'comprobante', 'path' => 'a.pdf', 'nombre' => 'a.pdf', 'usuario_id' => Usuario::first()->id]);

        $this->actingAs($this->rol('contador'))
            ->get(route('reportes.comprobantes', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/ComprobantesPendientes')
                ->has('reporte.comisiones', 1));
    }

    public function test_export_excel_viaticos_descarga_xlsx(): void
    {
        $this->seed();
        [$sol, $viajero] = $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 50000], 'cerrada');

        $res = $this->actingAs($this->rol('contador'))
            ->get(route('reportes.viaticos', ['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'export' => 'xlsx']));

        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', $res->headers->get('content-disposition'));
    }

    public function test_export_pdf_oficina_descarga_pdf(): void
    {
        $this->seed();
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 100000, 'total_a_pagar' => 100000]);
        ItemOficina::create(['solicitud_oficina_id' => $cab->id, 'nombre' => 'x', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 100000, 'subtotal' => 100000]);
        Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => Usuario::first()->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id,
            'estado' => 'pendiente_cierre', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        AbonoOficina::create(['solicitud_oficina_id' => $cab->id, 'monto' => 40000, 'fecha_pago' => '2026-01-15', 'soporte_path' => 'x', 'soporte_nombre' => 'x', 'usuario_id' => Usuario::first()->id]);

        $res = $this->actingAs($this->rol('admin'))
            ->get(route('reportes.oficina', ['desde' => '2026-01-01', 'hasta' => '2026-01-31', 'export' => 'pdf']));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_reajustes_lista_ajustes_del_rango(): void
    {
        $this->seed();
        [$sol, $viajero] = $this->comisionConRubros('2026-01-10', '2026-01-10', ['gasolina' => 1000], 'cerrada');
        AjusteComision::create([
            'solicitud_id' => $sol->id, 'viajero_comision_id' => $viajero->id,
            'solicitado_por' => Usuario::first()->id, 'tipo' => 'rubro', 'motivo' => 'x',
            'estado' => 'liquidado', 'rubro' => 'gasolina', 'cantidad' => 1, 'total_delta' => 15000,
        ]);

        $this->actingAs($this->rol('admin'))
            ->get(route('reportes.reajustes', ['desde' => '2026-01-01', 'hasta' => '2026-01-31']))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/Reajustes')
                ->where('reporte.num', 1)
                ->where('reporte.total_delta', 15000));
    }
}
