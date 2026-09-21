<?php
namespace Tests\Feature;

use App\Models\{Contrato, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Notifications\AvisoTransicionNotification;
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ObraNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_aprobar_notifica_residente_y_rrhh(): void
    {
        Notification::fake();
        $this->seed();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $cl = Usuario::factory()->create(); $cl->assignRole('contabilidad_lider');
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'contrato_id' => $contrato->id]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $m = app(MotorWorkflow::class);
        $m->aplicarTransicion($s, 'enviar', $residente);
        $m->aplicarTransicion($s->fresh(), 'cotizar', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'enviar_contabilidad', $rrhh);

        Notification::fake(); // aislar los avisos disparados por la aprobacion
        $m->aplicarTransicion($s->fresh(), 'aprobar', $cl);

        // Al aprobar (actor = contabilidad_lider): el residente (creador) y RR.HH.
        // (ya participo cotizando/enviando) reciben el aviso de seguimiento de obra,
        // aunque no sean el siguiente responsable de la etapa. El actor NO se auto-notifica.
        Notification::assertSentTo($residente, AvisoTransicionNotification::class,
            fn ($n) => $n->tipo === 'seguimiento');
        Notification::assertSentTo($rrhh, AvisoTransicionNotification::class,
            fn ($n) => $n->tipo === 'seguimiento');
        Notification::assertNotSentTo($cl, AvisoTransicionNotification::class);
    }

    public function test_el_detalle_de_obra_se_renderiza(): void
    {
        $this->seed();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $cab = SolicitudObra::create(['nombre_solicitante' => 'CAMILO', 'fecha_solicitud' => '2026-09-21']);
        \App\Models\ItemObra::create(['solicitud_obra_id' => $cab->id, 'especificacion' => 'CEMENTO', 'unidad' => 'BOLSA', 'cantidad' => 10]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->actingAs($residente)->get(route('solicitudes.show', $s))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Solicitudes/Detalle')
                ->where('solicitud.tipo.clave', 'OBR'));
    }
}
