<?php
namespace Tests\Feature;

use App\Models\{AbonoObra, ItemObra, SolicitudObra};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObraModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_solicitud_obra_con_items(): void
    {
        $s = SolicitudObra::create([
            'nombre_solicitante' => 'CAMILO (RESIDENTE)',
            'fecha_solicitud' => '2026-09-21',
        ]);
        ItemObra::create([
            'solicitud_obra_id' => $s->id, 'especificacion' => 'CEMENTO 42.5KG',
            'unidad' => 'BOLSA', 'cantidad' => 100,
        ]);
        $this->assertDatabaseHas('items_obra', ['especificacion' => 'CEMENTO 42.5KG', 'unidad' => 'BOLSA']);
        $this->assertSame(1, $s->items()->count());
    }

    public function test_saldo_y_pagado_se_calculan_contra_total_a_pagar(): void
    {
        $s = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'total_a_pagar' => 100000]);
        AbonoObra::create(['solicitud_obra_id' => $s->id, 'monto' => 30000, 'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x']);
        $this->assertEquals(30000.0, $s->fresh()->totalPagado());
        $this->assertEquals(70000.0, $s->fresh()->saldoPendiente());
    }

    public function test_subtotal_de_item_se_calcula_con_valor_unitario(): void
    {
        $s = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21']);
        $i = ItemObra::create(['solicitud_obra_id' => $s->id, 'especificacion' => 'ARENA', 'unidad' => 'VIAJE', 'cantidad' => 3, 'valor_unitario' => 5000]);
        $this->assertEquals(15000.0, (float) $i->fresh()->subtotal);
    }
}
