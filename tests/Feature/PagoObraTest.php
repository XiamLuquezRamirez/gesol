<?php
namespace Tests\Feature;

use App\Models\{AbonoObra, Contrato, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagoObraTest extends TestCase
{
    use RefreshDatabase;

    private function obraAprobada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $cl = Usuario::factory()->create(); $cl->assignRole('contabilidad_lider');
        $contrato = Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'contrato_id' => $contrato->id, 'total_a_pagar' => 100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $m = app(MotorWorkflow::class);
        $m->aplicarTransicion($s, 'enviar', $residente);
        $m->aplicarTransicion($s->fresh(), 'cotizar', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'enviar_contabilidad', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'aprobar', $cl);
        return $s->fresh();
    }

    private function cl(): Usuario { $u = Usuario::factory()->create(); $u->assignRole('contabilidad_lider'); return $u; }
    private function contador(): Usuario { $u = Usuario::factory()->create(); $u->assignRole('contador'); return $u; }

    public function test_primer_abono_lleva_a_pendiente_cierre(): void
    {
        $this->seed();
        Storage::fake('local');
        $s = $this->obraAprobada();
        $this->actingAs($this->cl())->post(route('obra.abono.store', $s), [
            'monto' => 40000, 'fecha_pago' => '2026-09-21',
            'soporte' => UploadedFile::fake()->create('pago.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('pendiente_cierre', $s->fresh()->estado);
        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
    }

    public function test_contador_aplica_retencion_porcentaje(): void
    {
        $this->seed();
        Storage::fake('local');
        $s = $this->obraAprobada();
        $abono = AbonoObra::create([
            'solicitud_obra_id' => $s->solicitable_id, 'monto' => 100000,
            'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x',
        ]);
        $this->actingAs($this->contador())->put(route('obra.abono.retencion', [$s, $abono]), [
            'retencion_tipo' => 'porcentaje', 'retencion_valor' => 10,
        ])->assertRedirect();
        $abono->refresh();
        $this->assertEquals(10000.0, (float) $abono->retencion_monto); // 10% de 100000
        $this->assertNotNull($abono->retencion_por);
    }

    public function test_contador_aplica_retencion_valor_fijo(): void
    {
        $this->seed();
        $s = $this->obraAprobada();
        $abono = AbonoObra::create(['solicitud_obra_id' => $s->solicitable_id, 'monto' => 100000, 'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x']);
        $this->actingAs($this->contador())->put(route('obra.abono.retencion', [$s, $abono]), [
            'retencion_tipo' => 'valor', 'retencion_valor' => 7500,
        ])->assertRedirect();
        $this->assertEquals(7500.0, (float) $abono->fresh()->retencion_monto);
    }

    public function test_solo_contador_aplica_retencion(): void
    {
        $this->seed();
        $s = $this->obraAprobada();
        $abono = AbonoObra::create(['solicitud_obra_id' => $s->solicitable_id, 'monto' => 1000, 'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x']);
        $this->actingAs($this->cl())->put(route('obra.abono.retencion', [$s, $abono]), [
            'retencion_tipo' => 'valor', 'retencion_valor' => 100,
        ])->assertForbidden();
    }

    public function test_no_puede_pagar_antes_de_aprobar(): void
    {
        $this->seed();
        Storage::fake('local');
        // Construir una obra en 'en_contabilidad' (sin aprobar aun).
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $contrato = Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'contrato_id' => $contrato->id, 'total_a_pagar' => 100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $m = app(MotorWorkflow::class);
        $m->aplicarTransicion($s, 'enviar', $residente);
        $m->aplicarTransicion($s->fresh(), 'cotizar', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'enviar_contabilidad', $rrhh);
        // estado = en_contabilidad, NO aprobada

        $this->actingAs($this->cl())->post(route('obra.abono.store', $s->fresh()), [
            'monto' => 40000, 'fecha_pago' => '2026-09-21',
            'soporte' => UploadedFile::fake()->create('pago.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }
}
