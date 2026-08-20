<?php
namespace Tests\Feature;

use App\Models\{Area, Contrato, Empleados, ItemOficina, Municipio, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Http\Resources\SolicitudResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValorEnListaSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(Solicitud $s): array
    {
        // Como lo hace el index: carga relaciones (por tipo, con morphWith para VIA)
        // y resuelve el Resource.
        $s->load([
            'tipoSolicitud', 'solicitante',
            'solicitable' => fn ($m) => $m->morphWith([
                SolicitudViaticos::class => ['municipios', 'viajeros.contrato'],
            ]),
        ]);
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

    /** Crea una comisión VIA con viajeros; cada elemento de $contratoIds asigna el contrato de ese viajero (o null). */
    private function comisionVia(array $municipioIds, array $contratoIds): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $lider = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x', 'total' => 0]);
        $cab->municipios()->sync($municipioIds);
        foreach ($contratoIds as $cid) {
            ViajeroComision::create([
                'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
                'contrato_id' => $cid,
                'motivo' => 'x', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
            ]);
        }
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id, 'estado' => 'enviada',
            'radicado' => Solicitud::generarRadicado($tipo), 'total' => 0,
        ]);
    }

    public function test_viaticos_expone_municipios_y_contratos_unicos(): void
    {
        $this->seed();
        $munis = Municipio::whereIn('nombre', ['Valledupar', 'Becerril'])->pluck('id')->all();
        $c1 = Contrato::create(['descripcion' => 'CTO Mantenimiento', 'objeto' => 'o']);
        $c2 = Contrato::create(['descripcion' => 'CTO Auditoría', 'objeto' => 'o']);

        // Dos viajeros con distinto contrato + uno que repite el primero -> contratos únicos = [CTO Mantenimiento, CTO Auditoría].
        $s = $this->comisionVia($munis, [$c1->id, $c2->id, $c1->id]);
        $via = $this->resolver($s)['viaticos'];

        $this->assertEqualsCanonicalizing(['Becerril', 'Valledupar'], $via['municipios']);
        $this->assertEqualsCanonicalizing(['CTO Mantenimiento', 'CTO Auditoría'], $via['contratos']);
    }

    public function test_viaticos_sin_contrato_expone_lista_vacia(): void
    {
        $this->seed();
        $munis = Municipio::whereIn('nombre', ['Valledupar'])->pluck('id')->all();
        // Un viajero sin contrato.
        $s = $this->comisionVia($munis, [null]);
        $via = $this->resolver($s)['viaticos'];

        $this->assertEquals(['Valledupar'], $via['municipios']);
        $this->assertSame([], $via['contratos']);
    }

    public function test_oficina_no_expone_bloque_viaticos(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 0]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'enviada',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        $this->assertArrayNotHasKey('viaticos', $this->resolver($s));
    }
}
