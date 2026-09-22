<?php
namespace Tests\Feature;

use App\Models\{Area, ConceptoPago, ItemOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficinaConceptoPagoTest extends TestCase
{
    use RefreshDatabase;

    private function liderYAreaGeneral(): array
    {
        $this->seed();
        $lider = Usuario::role('lider_area')->first();
        $areaGeneral = Area::where('es_general', true)->first() ?? Area::create(['nombre' => 'General', 'es_general' => true]);
        return [$lider, $areaGeneral];
    }

    public function test_solicitud_mezcla_elemento_y_pago(): void
    {
        [$lider, $areaGeneral] = $this->liderYAreaGeneral();
        // 'Gasolina' ya lo crea ConceptoPagoSeeder; reutilizarlo evita colisionar el indice unico.
        $gasolina = ConceptoPago::firstOrCreate(['nombre' => 'Gasolina']);

        $this->actingAs($lider)->post(route('oficina.store'), [
            'area_id'       => $areaGeneral->id,
            'urgencia'      => 'media',
            'justificacion' => 'Mixta',
            'items' => [
                ['nombre' => 'Resma', 'categoria' => 'producto', 'cantidad' => 2, 'costo_estimado' => 10000, 'notas' => '', 'concepto_pago_id' => null],
                ['nombre' => 'Tanqueo', 'categoria' => 'servicio', 'cantidad' => 1, 'costo_estimado' => 50000, 'notas' => 'Placa ABC123', 'concepto_pago_id' => $gasolina->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('items_oficina', ['nombre' => 'Resma', 'concepto_pago_id' => null]);
        $this->assertDatabaseHas('items_oficina', ['nombre' => 'Tanqueo', 'concepto_pago_id' => $gasolina->id]);
    }

    public function test_concepto_inexistente_es_rechazado(): void
    {
        [$lider, $areaGeneral] = $this->liderYAreaGeneral();

        $this->actingAs($lider)->post(route('oficina.store'), [
            'area_id'       => $areaGeneral->id,
            'urgencia'      => 'media',
            'justificacion' => 'x',
            'items' => [
                ['nombre' => 'X', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => null, 'notas' => '', 'concepto_pago_id' => 999999],
            ],
        ])->assertSessionHasErrors('items.0.concepto_pago_id');
    }

    public function test_item_tiene_relacion_concepto(): void
    {
        $c = ConceptoPago::firstOrCreate(['nombre' => 'Peaje']);
        $item = new ItemOficina();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $item->conceptoPago());
        $this->assertSame('concepto_pago_id', $item->conceptoPago()->getForeignKeyName());
    }
}
