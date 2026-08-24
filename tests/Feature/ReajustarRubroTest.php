<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReajustarRubroTest extends TestCase
{
    use RefreshDatabase;

    private function comision(string $estado = 'liquidada'): array
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        return [$s, $v, $lider];
    }

    public function test_lider_reajusta_gasolina_y_notifica(): void
    {
        Notification::fake();
        $this->seed();
        [$s, $v, $lider] = $this->comision('liquidada');

        $this->actingAs($lider)->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'gasolina', 'cantidad' => 3, 'motivo' => 'Camioneta empresa',
        ])->assertRedirect();

        $t = \App\Models\TransicionSolicitud::where('solicitud_id', $s->id)->where('accion','ajustar')->latest('id')->first();
        $this->assertEquals('rubro', $t->metadatos['tipo'] ?? null);
        $this->assertEquals('gasolina', $t->metadatos['rubro'] ?? null);
        $this->assertEquals(3, $t->metadatos['cantidad'] ?? null);
        $this->assertEquals('liquidada', $s->fresh()->estado);
        $this->assertTrue($s->solicitable->fresh()->requiere_reliquidacion);
        Notification::assertSentTo(Usuario::where('email','contador@demo.test')->firstOrFail(), AvisoTransicionNotification::class);
    }

    public function test_reajuste_de_rubro_en_cerrada_queda_anexo(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comision('cerrada');
        $this->actingAs($lider)->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'transporte', 'cantidad' => 1, 'motivo' => 'x',
        ])->assertRedirect();

        $this->assertEquals('cerrada', $s->fresh()->estado);
        $this->assertFalse($s->solicitable->fresh()->requiere_reliquidacion);
        // El reajuste de rubro post-cierre se registra como AjusteComision pendiente
        // de liquidacion (anexo), no como una transicion suelta.
        $this->assertDatabaseHas('ajustes_comision', [
            'solicitud_id' => $s->id, 'tipo' => 'rubro', 'rubro' => 'transporte',
            'estado' => 'pendiente_liquidacion',
        ]);
    }

    public function test_no_solicitante_no_reajusta_rubro(): void
    {
        $this->seed();
        [$s, $v] = $this->comision('liquidada');
        $otro = Usuario::where('email','contador@demo.test')->firstOrFail();
        $this->actingAs($otro)->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'gasolina', 'cantidad' => 1, 'motivo' => 'x',
        ])->assertForbidden();
    }

    public function test_rubro_invalido_es_rechazado(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comision('liquidada');
        $this->actingAs($lider)->from(route('solicitudes.show', $s))->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'desayuno', 'cantidad' => 1, 'motivo' => 'x',
        ])->assertSessionHasErrors('rubro');
    }
}
