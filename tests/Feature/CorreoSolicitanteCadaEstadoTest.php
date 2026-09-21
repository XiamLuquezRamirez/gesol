<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El solicitante debe recibir correo (canal mail) en CADA cambio de estado de su
 * solicitud, para trazabilidad. Estos tests verifican que en cada transicion se le
 * envia un AvisoTransicionNotification cuyo via() incluye 'mail'. No dependen de un
 * SMTP real: usan Notification::fake() e inspeccionan canales y tipo.
 */
class CorreoSolicitanteCadaEstadoTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderArea;
    private Usuario $liderComite;
    private Usuario $rrhh;
    private Usuario $contador;
    private Usuario $contabilidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor        = app(MotorWorkflow::class);
        $this->liderArea    = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->liderComite  = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->rrhh         = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $this->contador     = Usuario::where('email', 'contador@demo.test')->firstOrFail();
        $this->contabilidad = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
    }

    /** Aserta que el solicitante recibio un aviso por correo (via incluye 'mail') con el tipo dado. */
    private function assertCorreoAlSolicitante(Usuario $solicitante, string $tipoEsperado): void
    {
        Notification::assertSentTo(
            $solicitante,
            AvisoTransicionNotification::class,
            function (AvisoTransicionNotification $n) use ($solicitante, $tipoEsperado) {
                return $n->tipo === $tipoEsperado
                    && in_array('mail', $n->via($solicitante), true);
            }
        );
    }

    private function viaticosBorrador(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab  = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'X', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'x', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderComite->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
    }

    private function oficinaBorrador(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x']);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
    }

    public function test_viaticos_solicitante_recibe_correo_en_cada_avance(): void
    {
        Notification::fake();
        $s = $this->viaticosBorrador();

        // enviar: el actor ES el solicitante -> no se auto-notifica (no hay seguimiento aqui).
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderComite);

        // liquidar (contador): el solicitante debe recibir seguimiento por correo.
        $this->motor->aplicarTransicion($s->fresh(), 'liquidar', $this->contador);
        $this->assertCorreoAlSolicitante($this->liderComite, 'seguimiento');
    }

    public function test_viaticos_cierre_notifica_al_solicitante_por_correo(): void
    {
        Notification::fake();
        $s = $this->viaticosBorrador();
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($s->fresh(), 'liquidar', $this->contador);
        $this->motor->aplicarTransicion($s->fresh(), 'enviar_revision', $this->contador);
        $this->motor->aplicarTransicion($s->fresh(), 'enviar_gerencia', $this->contabilidad);
        $this->motor->aplicarTransicion($s->fresh(), 'cerrar', $this->contabilidad);

        // El cierre (estado final) antes NO avisaba a nadie; ahora avisa al solicitante.
        $this->assertCorreoAlSolicitante($this->liderComite, 'seguimiento');
        $this->assertSame('cerrada', $s->fresh()->estado);
    }

    public function test_viaticos_devolucion_avisa_al_solicitante_como_devuelta_no_seguimiento(): void
    {
        Notification::fake();
        $s = $this->viaticosBorrador();
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderComite);
        // El contador devuelve al solicitante.
        $this->motor->aplicarTransicion($s->fresh(), 'devolver', $this->contador);

        // Debe llegar el aviso de 'devuelta' (con motivo). No debe generarse ademas un
        // 'seguimiento' (el return del motor lo evita); al ser 'borrador' su estado, el
        // solicitante tambien recibe 'accion_requerida' (debe corregir), lo cual es correcto.
        $this->assertCorreoAlSolicitante($this->liderComite, 'devuelta');

        $tipos = Notification::sent($this->liderComite, AvisoTransicionNotification::class)
            ->map(fn ($n) => $n->tipo);
        $this->assertFalse($tipos->contains('seguimiento'), 'No debe duplicarse con seguimiento');
        $this->assertContains('devuelta', $tipos);
    }

    public function test_oficina_solicitante_recibe_correo_al_verificar_y_al_cerrar(): void
    {
        Notification::fake();
        $s = $this->oficinaBorrador();
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);        // actor = solicitante
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh); // -> verificada
        $this->assertCorreoAlSolicitante($this->liderArea, 'seguimiento');
    }

    public function test_oficina_cierre_notifica_al_solicitante_por_correo(): void
    {
        Notification::fake();
        $s = $this->oficinaBorrador();
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh);
        $this->motor->aplicarTransicion($s->fresh(), 'aprobar', $this->contabilidad); // -> aprobada
        $s->update(['estado' => 'pendiente_cierre']); // el abono lleva a pendiente_cierre (fuera del motor)
        $this->motor->aplicarTransicion($s->fresh(), 'cerrar', $this->contabilidad);

        $this->assertCorreoAlSolicitante($this->liderArea, 'seguimiento');
        $this->assertSame('cerrada', $s->fresh()->estado);
    }

    public function test_primer_abono_oficina_notifica_al_solicitante_por_correo(): void
    {
        Notification::fake();
        Storage::fake('local');

        // Llevar una OFI hasta 'aprobada' (lista para abonos).
        $s = $this->oficinaBorrador();
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh);
        $this->motor->aplicarTransicion($s->fresh(), 'aprobar', $this->contabilidad);

        // El contabilidad_lider registra el primer abono -> aprobada->pendiente_cierre.
        $this->actingAs($this->contabilidad)->post(route('oficina.abono.store', $s->fresh()), [
            'total_a_pagar' => 1000, 'monto' => 500, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('pago.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame('pendiente_cierre', $s->fresh()->estado);
        $this->assertCorreoAlSolicitante($this->liderArea, 'seguimiento');
    }

    public function test_no_se_notifica_al_solicitante_cuando_el_actua(): void
    {
        Notification::fake();
        $s = $this->viaticosBorrador();
        // El solicitante (lider.comite) ejecuta 'enviar'. No debe recibir 'seguimiento'
        // por su propia accion (solo el contador, como actor del siguiente paso).
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderComite);

        Notification::assertNotSentTo(
            $this->liderComite,
            AvisoTransicionNotification::class,
        );
    }
}
