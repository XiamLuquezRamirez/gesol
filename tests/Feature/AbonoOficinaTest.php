<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, SolicitudOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbonoOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_pagado_suma_abonos_y_saldo_es_total_menos_pagado(): void
    {
        $this->seed();
        $usuario = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $cabecera = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 100000,
        ]);

        AbonoOficina::create([
            'solicitud_oficina_id' => $cabecera->id, 'monto' => 40000, 'fecha_pago' => '2026-08-06',
            'soporte_path' => 'soportes_pago/a.pdf', 'soporte_nombre' => 'a.pdf', 'usuario_id' => $usuario->id,
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id' => $cabecera->id, 'monto' => 25000, 'fecha_pago' => '2026-08-07',
            'soporte_path' => 'soportes_pago/b.pdf', 'soporte_nombre' => 'b.pdf', 'usuario_id' => $usuario->id,
        ]);

        $this->assertEquals(65000.0, $cabecera->fresh()->totalPagado());
        $this->assertEquals(35000.0, $cabecera->fresh()->saldo());
    }
}
