<?php
namespace Tests\Feature;

use App\Models\ConceptoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConceptoPagoModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_crea_conceptos_base(): void
    {
        $this->seed(\Database\Seeders\ConceptoPagoSeeder::class);
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Gasolina']);
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Lavado de carro']);
        $this->assertTrue(ConceptoPago::where('nombre', 'Gasolina')->first()->activo);
    }

    public function test_nombre_es_unico(): void
    {
        ConceptoPago::create(['nombre' => 'Peaje']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ConceptoPago::create(['nombre' => 'Peaje']);
    }
}
