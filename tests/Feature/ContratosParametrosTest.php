<?php
namespace Tests\Feature;

use App\Models\{Contrato, Municipio};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_contrato_tiene_varios_municipios(): void
    {
        $this->seed();
        $ids = Municipio::take(2)->pluck('id')->all();
        $c = Contrato::create(['descripcion' => 'C-001', 'objeto' => 'Mantenimiento vial']);
        $c->municipios()->sync($ids);

        $this->assertEquals(2, $c->fresh()->municipios()->count());
        $this->assertEqualsCanonicalizing($ids, $c->fresh()->municipios->pluck('id')->all());
    }
}
