<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, SolicitudOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
