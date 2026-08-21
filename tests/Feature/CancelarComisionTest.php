<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CancelarComisionTest extends TestCase
{
    use RefreshDatabase;

    private function comision(string $estado = 'enviada'): Solicitud
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
    }

    public function test_solicitante_cancela_y_guarda_estado_previo(): void
    {
        Notification::fake();
        $this->seed();
        $s = $this->comision('liquidada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();

        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'reprogramada'])
            ->assertRedirect();

        $this->assertEquals('cancelada', $s->fresh()->estado);
        $this->assertEquals('liquidada', $s->fresh()->estado_previo);
        $this->assertDatabaseHas('transiciones_solicitud', ['solicitud_id' => $s->id, 'accion' => 'cancelar']);
        $rrhh = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        Notification::assertSentTo($rrhh, AvisoTransicionNotification::class);
    }

    public function test_reactivar_vuelve_al_estado_previo(): void
    {
        $this->seed();
        $s = $this->comision('liquidada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertRedirect();

        $this->actingAs($lider)->post(route('viaticos.reactivar', $s))->assertRedirect();
        $this->assertEquals('liquidada', $s->fresh()->estado);
        $this->assertNull($s->fresh()->estado_previo);
    }

    public function test_no_solicitante_no_cancela(): void
    {
        $this->seed();
        $s = $this->comision('enviada');
        $otro = Usuario::where('email','contador@demo.test')->firstOrFail();
        $this->actingAs($otro)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertForbidden();
    }

    public function test_no_cancela_cerrada(): void
    {
        $this->seed();
        $s = $this->comision('cerrada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertForbidden();
    }

    public function test_cancelada_no_aparece_en_panel_rrhh(): void
    {
        $this->seed();
        $s = $this->comision('enviada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $rrhh = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertRedirect();

        $this->actingAs($rrhh)->get(route('rrhh.comisiones', ['todos' => 1]))
            ->assertInertia(fn ($page) => $page->where('comisionados', fn ($d) => count($d) === 0));
    }
}
