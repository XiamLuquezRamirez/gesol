<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OficinaRrhhTest extends TestCase
{
    use RefreshDatabase;

    public function test_rrhh_ve_solicitudes_de_oficina_con_abonos(): void
    {
        $this->seed();
        $rrhh  = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $lider = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $cl    = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario'=>'','urgencia'=>'media','justificacion'=>'x','total'=>50000]);
        ItemOficina::create(['solicitud_oficina_id'=>$cab->id,'nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>50000,'subtotal'=>50000]);
        $s = Solicitud::create([
            'tipo_solicitud_id'=>$tipo->id,'solicitante_id'=>$lider->id,'area_id'=>Area::first()->id,
            'solicitable_type'=>SolicitudOficina::class,'solicitable_id'=>$cab->id,'estado'=>'pendiente_cierre',
            'radicado'=>Solicitud::generarRadicado($tipo),
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id'=>$cab->id,'monto'=>50000,'fecha_pago'=>'2026-08-06',
            'soporte_path'=>'soportes_pago/x.pdf','soporte_nombre'=>'x.pdf','usuario_id'=>$cl->id,
        ]);

        $this->actingAs($rrhh)
            ->get(route('rrhh.comisiones'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rrhh/Comisiones')
                ->has('oficina', 1)
                ->where('oficina.0.radicado', $s->radicado)
                ->where('oficina.0.pagado', fn ($v) => (float) $v === 50000.0)
                ->where('oficina.0.saldo', fn ($v) => (float) $v === 0.0)
            );
    }
}
