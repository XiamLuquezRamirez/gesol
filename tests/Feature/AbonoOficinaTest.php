<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    private function aprobada(): Solicitud
    {
        $this->seed();
        $motor = app(MotorWorkflow::class);
        $lider = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $rrhh  = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $cl    = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario'=>'','urgencia'=>'media','justificacion'=>'x','total'=>100000]);
        ItemOficina::create(['solicitud_oficina_id'=>$cab->id,'nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>100000,'subtotal'=>100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id'=>$tipo->id,'solicitante_id'=>$lider->id,'area_id'=>Area::first()->id,
            'solicitable_type'=>SolicitudOficina::class,'solicitable_id'=>$cab->id,'estado'=>'borrador',
            'radicado'=>Solicitud::generarRadicado($tipo),
        ]);
        $motor->aplicarTransicion($s, 'enviar', $lider);
        $motor->aplicarTransicion($s->fresh(), 'verificar', $rrhh);
        $motor->aplicarTransicion($s->fresh(), 'aprobar', $cl);
        return $s->fresh();
    }

    public function test_primer_abono_pasa_la_solicitud_a_pendiente_cierre(): void
    {
        Storage::fake('local');
        $s  = $this->aprobada();
        $cl = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();

        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 40000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('pago1.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);
        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
        $this->assertEquals(60000.0, $s->solicitable->fresh()->saldo());
    }

    public function test_un_abono_puede_cubrir_la_totalidad(): void
    {
        Storage::fake('local');
        $s  = $this->aprobada();
        $cl = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();

        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 100000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('total.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals(0.0, $s->solicitable->fresh()->saldo());
        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);
    }

    public function test_solo_contabilidad_lider_registra_abonos(): void
    {
        Storage::fake('local');
        $s   = $this->aprobada();
        $cont = Usuario::where('email','contador@demo.test')->firstOrFail();

        $this->actingAs($cont)->post(route('oficina.abono.store', $s), [
            'monto' => 1000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_descarga_de_soporte_disponible_para_quien_ve_el_detalle(): void
    {
        Storage::fake('local');
        $s  = $this->aprobada();
        $cl = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $rrhh = Usuario::where('email','rrhh@demo.test')->firstOrFail();

        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 50000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf'),
        ]);
        $abono = $s->solicitable->fresh()->abonos()->first();

        $this->actingAs($rrhh)
            ->get(route('oficina.abono.soporte', [$s, $abono->id]))
            ->assertOk();
    }
}
