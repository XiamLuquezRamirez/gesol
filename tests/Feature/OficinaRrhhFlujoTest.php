<?php
namespace Tests\Feature;

use App\Models\{Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcciones del flujo de oficina:
 *  1. El cierre (pendiente_cierre) solo lo hace contabilidad_lider (no todos los lider_area).
 *  2. Cuando RR.HH. es el solicitante, la solicitud va directo a contabilidad (salta verificacion).
 *  3. Un borrador no aparece como pendiente para otros usuarios del rol creador.
 */
class OficinaRrhhFlujoTest extends TestCase
{
    use RefreshDatabase;

    private function crearOficinaBorrador(Usuario $solicitante): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x']);
        ItemOficina::create(['solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000]);
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $solicitante->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
    }

    public function test_pendiente_cierre_no_le_aparece_a_los_lideres_de_area(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $otroLider = Usuario::factory()->create(); $otroLider->assignRole('lider_area');
        $s = $this->crearOficinaBorrador($lider);
        // Forzar el estado pendiente_cierre.
        $s->update(['estado' => 'pendiente_cierre']);

        $motor = app(MotorWorkflow::class);
        // Ningun lider de area tiene acciones disponibles en pendiente_cierre (solo contabilidad_lider).
        $this->assertEmpty($motor->accionesDisponibles($s->fresh(), $lider));
        $this->assertEmpty($motor->accionesDisponibles($s->fresh(), $otroLider));

        // El lider de contabilidad SI puede cerrar.
        $cl = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $this->assertNotEmpty($motor->accionesDisponibles($s->fresh(), $cl));
    }

    public function test_rrhh_solicitante_va_directo_a_contabilidad(): void
    {
        $this->seed();
        $rrhh = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();

        $general = Area::where('es_general', 1)->firstOrFail();
        $res = $this->actingAs($rrhh)->post(route('oficina.store'), [
            'urgencia' => 'media', 'justificacion' => 'Necesito papeleria',
            'area_id' => $general->id,
            'items' => [['nombre' => 'Resma', 'categoria' => 'producto', 'cantidad' => 2, 'costo_estimado' => 15000]],
            'enviar' => true,
        ]);
        $res->assertRedirect();

        $s = Solicitud::whereHasMorph('solicitable', SolicitudOficina::class)->latest('id')->first();
        // Salta la verificacion de RR.HH.: queda en 'verificada' (lista para contabilidad).
        $this->assertSame('verificada', $s->estado);
    }

    public function test_lider_area_solicitante_pasa_por_verificacion_rrhh(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();

        $general = Area::where('es_general', 1)->firstOrFail();
        $this->actingAs($lider)->post(route('oficina.store'), [
            'urgencia' => 'media', 'justificacion' => 'x',
            'area_id' => $general->id,
            'items' => [['nombre' => 'Mouse', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000]],
            'enviar' => true,
        ])->assertRedirect();

        $s = Solicitud::whereHasMorph('solicitable', SolicitudOficina::class)->latest('id')->first();
        // El lider de area SI pasa por RR.HH.: queda en 'enviada'.
        $this->assertSame('enviada', $s->estado);
    }

    public function test_borrador_no_aparece_en_pendientes_de_otro_lider(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $otroLider = Usuario::factory()->create(); $otroLider->assignRole('lider_area');
        $this->crearOficinaBorrador($lider); // queda en borrador

        // El otro lider de area NO debe ver ese borrador en su cola de pendientes.
        $this->actingAs($otroLider)
            ->get(route('solicitudes.index', ['tab' => 'pendientes']))
            ->assertInertia(fn ($p) => $p->has('solicitudes.data', 0));
    }
}
