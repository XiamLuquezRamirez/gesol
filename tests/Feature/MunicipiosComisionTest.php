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
}
