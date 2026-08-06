<?php
namespace Tests\Feature;

use App\Models\{Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CotizacionOficinaTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderArea;
    private Usuario $rrhh;
    private Usuario $contabilidadLider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->liderArea         = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->rrhh              = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
    }

    private function crearSolicitud(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cabecera = SolicitudOficina::create([
            'beneficiario' => 'Juan', 'urgencia' => 'media', 'justificacion' => 'x',
        ]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id,
            'area_id' => Area::first()->id, 'solicitable_type' => SolicitudOficina::class,
            'solicitable_id' => $cabecera->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
    }

    private function enviada(): Solicitud
    {
        $s = $this->crearSolicitud();
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);
        return $s->fresh();
    }

    /** Lleva la solicitud a 'rechazada' (por falta de cotizacion). */
    private function rechazada(): Solicitud
    {
        $s = $this->enviada();
        $this->motor->aplicarTransicion($s, 'verificar', $this->rrhh);
        $this->motor->aplicarTransicion($s->fresh(), 'rechazar', $this->contabilidadLider);
        return $s->fresh();
    }

    private function pdf(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 100, 'application/pdf');
    }

    public function test_anexar_multiples_archivos_se_acumulan(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones'        => [$this->pdf('a.pdf'), $this->pdf('b.pdf')],
            'comentario_contador' => 'Dos cotizaciones.',
        ])->assertRedirect();

        // Una tercera subida se acumula, no reemplaza.
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('c.pdf')],
        ])->assertRedirect();

        $this->assertEquals(3, $solicitud->solicitable->fresh()->cotizaciones()->count());
        $this->assertEquals('Dos cotizaciones.', $solicitud->solicitable->fresh()->comentario_contador);
    }

    public function test_eliminar_una_cotizacion_individual(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')],
        ]);

        $primera = $solicitud->solicitable->fresh()->cotizaciones()->first();

        $this->actingAs($this->rrhh)
            ->delete(route('oficina.cotizacion.eliminar', [$solicitud, $primera->id]))
            ->assertRedirect();

        $this->assertEquals(1, $solicitud->solicitable->fresh()->cotizaciones()->count());
        Storage::disk('local')->assertMissing($primera->path);
    }

    public function test_rechazada_puede_anexar_y_reenviar_a_contabilidad(): void
    {
        Storage::fake('local');
        $solicitud = $this->rechazada();
        $this->assertEquals('rechazada', $solicitud->estado);

        // RR. HH. anexa la cotizacion que faltaba.
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('cotiz.pdf')],
        ])->assertRedirect();

        // Y reenvia a contabilidad: rechazada -> verificada.
        $this->actingAs($this->rrhh)
            ->post(route('solicitudes.transicion', $solicitud), ['accion' => 'reenviar'])
            ->assertRedirect();

        $this->assertEquals('verificada', $solicitud->fresh()->estado);
        $this->assertEquals(1, $solicitud->solicitable->fresh()->cotizaciones()->count());
    }

    public function test_lider_area_puede_anexar_cuando_esta_rechazada(): void
    {
        Storage::fake('local');
        $solicitud = $this->rechazada();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.cotizacion.anexar', $solicitud), [
                'cotizaciones' => [$this->pdf('cotiz.pdf')],
            ])
            ->assertRedirect();

        $this->assertEquals(1, $solicitud->solicitable->fresh()->cotizaciones()->count());
    }

    public function test_no_autorizado_no_puede_anexar(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        // El lider de contabilidad no gestiona cotizaciones.
        $this->actingAs($this->contabilidadLider)
            ->post(route('oficina.cotizacion.anexar', $solicitud), [
                'cotizaciones' => [$this->pdf('x.pdf')],
            ])
            ->assertForbidden();
    }

    public function test_no_se_puede_anexar_en_borrador(): void
    {
        Storage::fake('local');
        $solicitud = $this->crearSolicitud(); // borrador

        $this->actingAs($this->rrhh)
            ->post(route('oficina.cotizacion.anexar', $solicitud), [
                'cotizaciones' => [$this->pdf('x.pdf')],
            ])
            ->assertForbidden();
    }

    public function test_descarga_controlada(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('cotiz.pdf')],
        ]);
        $archivo = $solicitud->solicitable->fresh()->cotizaciones()->first();

        $this->actingAs($this->contabilidadLider)
            ->get(route('oficina.cotizacion.descargar', [$solicitud, $archivo->id]))
            ->assertOk();
    }

    public function test_archivo_invalido_es_rechazado(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        $this->actingAs($this->rrhh)
            ->post(route('oficina.cotizacion.anexar', $solicitud), [
                'cotizaciones' => [UploadedFile::fake()->create('malo.exe', 100, 'application/octet-stream')],
            ])
            ->assertSessionHasErrors('cotizaciones.0');
    }

    public function test_solo_el_autor_puede_eliminar_su_cotizacion(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        // RR. HH. sube la cotizacion (queda como autor).
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('a.pdf')],
        ]);
        $cotiz = $solicitud->solicitable->fresh()->cotizaciones()->first();

        // El lider de area (otro usuario con rol de anexar) NO puede eliminarla.
        $this->actingAs($this->liderArea)
            ->delete(route('oficina.cotizacion.eliminar', [$solicitud, $cotiz->id]))
            ->assertForbidden();

        // El autor si puede.
        $this->actingAs($this->rrhh)
            ->delete(route('oficina.cotizacion.eliminar', [$solicitud, $cotiz->id]))
            ->assertRedirect();
        $this->assertEquals(0, $solicitud->solicitable->fresh()->cotizaciones()->count());
    }

    public function test_actualizar_reemplaza_el_archivo_del_autor(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('viejo.pdf')],
        ]);
        $cotiz = $solicitud->solicitable->fresh()->cotizaciones()->first();
        $pathViejo = $cotiz->path;

        $this->actingAs($this->rrhh)
            ->post(route('oficina.cotizacion.actualizar', [$solicitud, $cotiz->id]), [
                'cotizacion' => $this->pdf('nuevo.pdf'),
            ])
            ->assertRedirect();

        $cotiz->refresh();
        $this->assertEquals('nuevo.pdf', $cotiz->nombre_original);
        $this->assertNotEquals($pathViejo, $cotiz->path);
        Storage::disk('local')->assertMissing($pathViejo);
        Storage::disk('local')->assertExists($cotiz->path);
    }

    public function test_no_autor_no_puede_actualizar_la_cotizacion(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        // RR. HH. sube la cotizacion (queda como autor).
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('a.pdf')],
        ]);
        $cotiz = $solicitud->solicitable->fresh()->cotizaciones()->first();

        // Otro usuario (no autor) no puede reemplazar el archivo.
        $this->actingAs($this->liderArea)
            ->post(route('oficina.cotizacion.actualizar', [$solicitud, $cotiz->id]), [
                'cotizacion' => $this->pdf('ajeno.pdf'),
            ])
            ->assertForbidden();

        // El archivo original sigue intacto.
        Storage::disk('local')->assertExists($cotiz->fresh()->path);
    }

    public function test_ni_el_autor_puede_gestionar_si_la_solicitud_esta_cerrada(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        // RR. HH. sube la cotizacion (queda como autor).
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('a.pdf')],
        ]);
        $cotiz = $solicitud->solicitable->fresh()->cotizaciones()->first();

        // Una vez cerrada, ni el autor puede eliminar ni actualizar la cotizacion.
        $solicitud->update(['estado' => 'cerrada']);

        $this->actingAs($this->rrhh)
            ->delete(route('oficina.cotizacion.eliminar', [$solicitud, $cotiz->id]))
            ->assertForbidden();

        $this->actingAs($this->rrhh)
            ->post(route('oficina.cotizacion.actualizar', [$solicitud, $cotiz->id]), [
                'cotizacion' => $this->pdf('nuevo.pdf'),
            ])
            ->assertForbidden();

        $this->assertEquals(1, $solicitud->solicitable->fresh()->cotizaciones()->count());
    }
}
