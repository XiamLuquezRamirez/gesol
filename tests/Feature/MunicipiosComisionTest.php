<?php
namespace Tests\Feature;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipiosComisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_crea_el_catalogo_de_municipios(): void
    {
        $this->seed();
        $this->assertGreaterThan(0, Municipio::count());
        $this->assertDatabaseHas('municipios', ['nombre' => 'Valledupar']);
    }

    public function test_una_comision_sincroniza_varios_municipios(): void
    {
        $this->seed();
        $cab = \App\Models\SolicitudViaticos::create([
            'nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x',
        ]);
        $ids = \App\Models\Municipio::take(3)->pluck('id')->all();
        $cab->municipios()->sync($ids);

        $this->assertEquals(3, $cab->fresh()->municipios()->count());
        $this->assertEqualsCanonicalizing($ids, $cab->fresh()->municipios->pluck('id')->all());
    }
}
