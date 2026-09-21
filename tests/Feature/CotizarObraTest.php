<?php
namespace Tests\Feature;

use App\Models\{Contrato, ItemObra, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CotizarObraTest extends TestCase
{
    use RefreshDatabase;

    private function obraEnviada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21']);
        ItemObra::create(['solicitud_obra_id' => $cab->id, 'especificacion' => 'CEMENTO', 'unidad' => 'BOLSA', 'cantidad' => 10]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        app(MotorWorkflow::class)->aplicarTransicion($s, 'enviar', $residente);
        return $s->fresh();
    }

    private function rrhh(): Usuario { $u = Usuario::factory()->create(); $u->assignRole('rrhh'); return $u; }

    public function test_rrhh_cotiza_valores_de_items(): void
    {
        $this->seed();
        $s = $this->obraEnviada();
        $item = $s->solicitable->items->first();

        $this->actingAs($this->rrhh())->put(route('obra.cotizar', $s), [
            'total_a_pagar' => 500000,
            'items' => [['id' => $item->id, 'valor_unitario' => 5000]],
        ])->assertRedirect();

        $this->assertEquals(50000.0, (float) $item->fresh()->subtotal); // 10 × 5000
        $this->assertSame('cotizada', $s->fresh()->estado);
    }

    public function test_no_envia_a_contabilidad_sin_contrato(): void
    {
        $this->seed();
        $s = $this->obraEnviada();
        app(MotorWorkflow::class)->aplicarTransicion($s, 'cotizar', $this->rrhh());

        $this->actingAs($this->rrhh())->post(route('solicitudes.transicion', $s->fresh()), [
            'accion' => 'enviar_contabilidad',
        ])->assertSessionHasErrors('accion');
        $this->assertSame('cotizada', $s->fresh()->estado);
    }

    public function test_relaciona_contrato_y_envia_a_contabilidad(): void
    {
        $this->seed();
        $s = $this->obraEnviada();
        app(MotorWorkflow::class)->aplicarTransicion($s, 'cotizar', $this->rrhh());
        $contrato = Contrato::first() ?? Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);

        $this->actingAs($this->rrhh())->put(route('obra.contrato', $s->fresh()), ['contrato_id' => $contrato->id])->assertRedirect();
        $this->assertEquals($contrato->id, $s->solicitable->fresh()->contrato_id);

        $this->actingAs($this->rrhh())->post(route('solicitudes.transicion', $s->fresh()), [
            'accion' => 'enviar_contabilidad',
        ])->assertRedirect();
        $this->assertSame('en_contabilidad', $s->fresh()->estado);
    }
}
