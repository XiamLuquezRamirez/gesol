<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TotalAPagarOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_pendiente_se_calcula_contra_total_a_pagar(): void
    {
        $this->seed();
        $u = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $c = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
            'total' => 999999, 'total_a_pagar' => 100000,
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id' => $c->id, 'monto' => 40000, 'fecha_pago' => '2026-08-12',
            'soporte_path' => 'soportes_pago/a.pdf', 'soporte_nombre' => 'a.pdf', 'usuario_id' => $u->id,
        ]);

        // Saldo contra total_a_pagar (100000), no contra el estimado (999999).
        $this->assertEquals(60000.0, $c->fresh()->saldoPendiente());
        $this->assertFalse($c->fresh()->estaPagadaCompleta());
    }

    public function test_sin_total_a_pagar_no_hay_saldo(): void
    {
        $c = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 50000,
        ]);
        $this->assertNull($c->total_a_pagar);
        $this->assertEquals(0.0, $c->saldoPendiente());
        $this->assertFalse($c->estaPagadaCompleta());
    }

    public function test_esta_pagada_completa_cuando_pagado_alcanza_el_total(): void
    {
        $this->seed();
        $u = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $c = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
            'total' => 0, 'total_a_pagar' => 100000,
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id' => $c->id, 'monto' => 100000, 'fecha_pago' => '2026-08-12',
            'soporte_path' => 'soportes_pago/a.pdf', 'soporte_nombre' => 'a.pdf', 'usuario_id' => $u->id,
        ]);

        $this->assertEquals(0.0, $c->fresh()->saldoPendiente());
        $this->assertTrue($c->fresh()->estaPagadaCompleta());
    }

    private function aprobada(): Solicitud
    {
        $this->seed();
        $motor = app(MotorWorkflow::class);
        $lider = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $rrhh  = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $cl    = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario'=>'','urgencia'=>'media','justificacion'=>'x','total'=>100000]);
        ItemOficina::create(['solicitud_oficina_id'=>$cab->id,'nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>100000,'subtotal'=>100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id'=>$tipo->id,'solicitante_id'=>$lider->id,'area_id'=>Area::first()->id,
            'solicitable_type'=>SolicitudOficina::class,'solicitable_id'=>$cab->id,'estado'=>'borrador',
            'radicado'=>Solicitud::generarRadicado($tipo),
        ]);
        $motor->aplicarTransicion($s, 'enviar', $lider);
        $motor->aplicarTransicion($s->fresh(), 'verificar', $rrhh);
        $motor->aplicarTransicion($s->fresh(), 'aprobar', $cl);
        return $s->fresh();
    }

    private function cl(): Usuario
    {
        return Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
    }

    public function test_primer_abono_guarda_el_total_a_pagar(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        $this->actingAs($this->cl())->post(route('oficina.abono.store', $s), [
            'total_a_pagar' => 80000, 'monto' => 30000, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $cab = $s->solicitable->fresh();
        $this->assertEquals(80000.0, (float) $cab->total_a_pagar);
        $this->assertEquals(50000.0, $cab->saldoPendiente());
    }

    public function test_primer_abono_requiere_total_a_pagar(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'monto' => 30000, 'fecha_pago' => '2026-08-12',
                'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('total_a_pagar');
    }

    public function test_primer_abono_monto_no_puede_superar_el_total(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'total_a_pagar' => 50000, 'monto' => 60000, 'fecha_pago' => '2026-08-12',
                'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('monto');

        $this->assertEquals(0, $s->solicitable->fresh()->abonos()->count());
    }

    public function test_abono_siguiente_no_puede_superar_el_saldo(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        // Primer abono: total 100000, paga 40000 -> saldo 60000.
        $this->actingAs($this->cl())->post(route('oficina.abono.store', $s), [
            'total_a_pagar' => 100000, 'monto' => 40000, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

        // Segundo abono de 70000 excede el saldo (60000) -> error.
        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'monto' => 70000, 'fecha_pago' => '2026-08-13',
                'soporte' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('monto');

        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
    }

    public function test_pago_unico_deja_saldo_cero_y_no_admite_mas(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        // Pago total: monto = total_a_pagar.
        $this->actingAs($this->cl())->post(route('oficina.abono.store', $s), [
            'total_a_pagar' => 100000, 'monto' => 100000, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals(0.0, $s->solicitable->fresh()->saldoPendiente());
        $this->assertTrue($s->solicitable->fresh()->estaPagadaCompleta());

        // Un abono adicional se rechaza (saldo 0).
        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'monto' => 1, 'fecha_pago' => '2026-08-13',
                'soporte' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('monto');

        $this->assertEquals(1, $s->solicitable->fresh()->abonos()->count());
    }
}
