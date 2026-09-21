<?php
namespace Tests\Feature;

use App\Models\{ItemObra, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ObraListadoTest extends TestCase
{
    use RefreshDatabase;

    private function obraEnviada(): array
    {
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21']);
        ItemObra::create(['solicitud_obra_id' => $cab->id, 'especificacion' => 'CEMENTO', 'unidad' => 'BOLSA', 'cantidad' => 10]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        app(MotorWorkflow::class)->aplicarTransicion($s, 'enviar', $residente);
        return [$s->fresh(), $residente];
    }

    public function test_obra_enviada_aparece_pendiente_para_rrhh(): void
    {
        $this->seed();
        [$s, $residente] = $this->obraEnviada();
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');

        $this->actingAs($rrhh)
            ->get(route('solicitudes.index', ['tab' => 'pendientes']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('solicitudes.data', 1));
    }

    public function test_obra_aparece_en_mis_solicitudes_del_residente(): void
    {
        $this->seed();
        [$s, $residente] = $this->obraEnviada();

        $this->actingAs($residente)
            ->get(route('solicitudes.index', ['tab' => 'mias']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('solicitudes.data', 1));
    }

    public function test_detalle_de_obra_no_rompe_el_listado(): void
    {
        // El listado carga relaciones por morphWith; con OBR no debe fallar.
        $this->seed();
        [$s, $residente] = $this->obraEnviada();
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $this->actingAs($rrhh)->get(route('solicitudes.index', ['tab' => 'pendientes']))->assertOk();
    }
}
