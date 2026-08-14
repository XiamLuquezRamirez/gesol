<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConteosPestanasSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderArea;
    private Usuario $liderComite;
    private Usuario $rrhh;
    private Usuario $contabilidadLider;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->liderArea         = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->liderComite       = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->rrhh              = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $this->contador          = Usuario::where('email', 'contador@demo.test')->firstOrFail();
    }

    /** Crea una solicitud de oficina y la deja en el estado indicado avanzando el flujo. */
    private function oficina(string $hasta): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 1000]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);       // enviada
        if ($hasta === 'enviada') return $s->fresh();
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh); // verificada
        if ($hasta === 'verificada') return $s->fresh();
        $this->motor->aplicarTransicion($s->fresh(), 'aprobar', $this->contabilidadLider); // aprobada
        $s->update(['estado' => 'pendiente_cierre']); // el abono la llevaria aqui; se simula
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

    public function test_index_expone_conteos_con_las_tres_claves(): void
    {
        $this->actingAs($this->liderArea)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Solicitudes/Index')
                ->has('conteos.pendientes')
                ->has('conteos.pendientes_cierre')
                ->has('conteos.pendientes_lider')
            );
    }

    public function test_conteo_pendientes_cierre_solo_para_quien_ve_la_cola(): void
    {
        $this->oficina('cerrar'); // queda en pendiente_cierre

        $this->actingAs($this->contabilidadLider)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_cierre', 1));

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_cierre', 0));
    }

    public function test_conteo_pendientes_lider_solo_para_contador(): void
    {
        $this->oficina('verificada');
        $this->viaticosRevisada();

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_lider', 2));

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_lider', 0));
    }

    public function test_conteo_pendientes_de_accion_cuenta_las_accionables(): void
    {
        $this->oficina('enviada');

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes', 1));
    }
}
