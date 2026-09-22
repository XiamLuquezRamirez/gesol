<?php
namespace Tests\Feature;

use App\Models\{ConceptoPago, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConceptoPagoParametrosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        $this->seed();
        return Usuario::where('email', 'admin@demo.test')->firstOrFail();
    }

    public function test_crear_concepto(): void
    {
        $this->actingAs($this->admin())
            ->post(route('parametros.conceptos.store'), ['nombre' => 'Grúa'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Grúa']);
    }

    public function test_actualizar_concepto(): void
    {
        $c = ConceptoPago::create(['nombre' => 'Taxi']);
        $this->actingAs($this->admin())
            ->put(route('parametros.conceptos.update', $c), ['nombre' => 'Taxi urbano', 'activo' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conceptos_pago', ['id' => $c->id, 'nombre' => 'Taxi urbano', 'activo' => false]);
    }

    public function test_eliminar_concepto(): void
    {
        $c = ConceptoPago::create(['nombre' => 'Otro']);
        $this->actingAs($this->admin())
            ->delete(route('parametros.conceptos.destroy', $c))->assertRedirect();
        $this->assertDatabaseMissing('conceptos_pago', ['id' => $c->id]);
    }

    public function test_nombre_unico_en_crear(): void
    {
        ConceptoPago::create(['nombre' => 'Gasolina']);
        $this->actingAs($this->admin())
            ->post(route('parametros.conceptos.store'), ['nombre' => 'Gasolina'])
            ->assertSessionHasErrors('nombre');
    }
}
