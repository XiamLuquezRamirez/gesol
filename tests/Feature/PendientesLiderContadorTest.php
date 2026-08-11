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

    public function test_el_detalle_no_expone_acciones_al_contador(): void
    {
        $s = $this->oficinaVerificada();

        // La vista de detalle no debe traer botones de accion para el contador.
        $this->actingAs($this->contador)
            ->get(route('solicitudes.show', $s))
            ->assertInertia(fn ($page) => $page
                ->component('Solicitudes/Detalle')
                ->where('acciones', [])
            );
    }

    public function test_contador_no_ve_oficina_en_estado_no_contemplado(): void
    {
        // Una oficina en 'enviada' (aun no verificada) no es visible para el contador:
        // el acceso de lectura esta acotado a 'verificada'/'revisada', no es amplio.
        $s = $this->oficinaVerificada();
        $s->update(['estado' => 'enviada']);

        $this->assertFalse($this->contador->can('verDetalle', $s->fresh()));
        $this->actingAs($this->contador)->get(route('solicitudes.show', $s))->assertForbidden();
    }

    public function test_tab_pendientes_lider_lista_oficina_verificada_y_viaticos_revisada(): void
    {
        $ofi = $this->oficinaVerificada();
        $via = $this->viaticosRevisada();

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page
                ->component('Solicitudes/Index')
                ->has('solicitudes.data', 2)
            );
    }

    public function test_tab_pendientes_lider_excluye_otros_estados(): void
    {
        // Una oficina en 'enviada' (aun no verificada) no debe aparecer.
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
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea); // queda 'enviada'

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page->has('solicitudes.data', 0));
    }

    public function test_tab_pendientes_lider_vacio_para_no_contador(): void
    {
        $this->oficinaVerificada();

        // RR. HH. no ve esta cola (es exclusiva del contador).
        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page->has('solicitudes.data', 0));
    }

    public function test_tab_pendientes_lider_excluye_viaticos_en_otro_estado(): void
    {
        // Una comision en 'liquidada' (aun no enviada al lider) no debe aparecer.
        $via = $this->viaticosRevisada();
        $via->update(['estado' => 'liquidada']);

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page->has('solicitudes.data', 0));
    }

    public function test_tab_pendientes_lider_ordena_las_mas_antiguas_primero(): void
    {
        // Dos solicitudes verificadas; la creada antes debe salir primero.
        $vieja = $this->oficinaVerificada();
        $vieja->update(['created_at' => now()->subDays(3)]);
        $nueva = $this->oficinaVerificada();
        $nueva->update(['created_at' => now()->subDay()]);

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page
                ->has('solicitudes.data', 2)
                ->where('solicitudes.data.0.id', $vieja->id)
            );
    }
}
