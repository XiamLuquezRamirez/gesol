<?php
namespace Tests\Feature;

use App\Models\{Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SolicitudesRevisadasTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private TipoSolicitud $tipo;
    private Usuario $liderArea;
    private Usuario $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor     = app(MotorWorkflow::class);
        $this->tipo      = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $this->liderArea = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->rrhh      = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
    }

    private function crearSolicitud(): Solicitud
    {
        $cabecera = SolicitudOficina::create([
            'beneficiario'  => $this->liderArea->name,
            'urgencia'      => 'media',
            'justificacion' => 'Material de oficina.',
        ]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id,
            'nombre'               => 'Mouse',
            'categoria'            => 'producto',
            'cantidad'             => 1,
            'costo_estimado'       => 35000,
            'subtotal'             => 35000,
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $this->tipo->id,
            'solicitante_id'    => $this->liderArea->id,
            'area_id'           => Area::first()->id,
            'solicitable_type'  => SolicitudOficina::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'borrador',
            'radicado'          => Solicitud::generarRadicado($this->tipo),
        ]);
    }

    public function test_solicitud_revisada_aparece_en_pestana_revisadas(): void
    {
        $solicitud = $this->crearSolicitud();
        // El lider la envia; RRHH la verifica (deja rastro de transicion de RRHH).
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->rrhh);

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index', ['tab' => 'revisadas']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Solicitudes/Index')
                ->where('solicitudes.data', fn ($data) => collect($data)->pluck('id')->contains($solicitud->id))
            );
    }

    public function test_solicitud_no_revisada_no_aparece_en_revisadas(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);
        // RRHH nunca actua sobre ella.

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index', ['tab' => 'revisadas']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Solicitudes/Index')
                ->where('solicitudes.data', fn ($data) => !collect($data)->pluck('id')->contains($solicitud->id))
            );
    }

    public function test_revisadas_permanece_visible_en_estado_final(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->rrhh);
        // Se lleva hasta rechazada por contabilidad; RRHH ya no tiene accion, pero reviso antes.
        $contab = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $this->motor->aplicarTransicion($solicitud->fresh(), 'rechazar', $contab);

        $this->assertEquals('rechazada', $solicitud->fresh()->estado);

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index', ['tab' => 'revisadas']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Solicitudes/Index')
                ->where('solicitudes.data', fn ($data) => collect($data)->pluck('id')->contains($solicitud->id))
            );
    }
}
