<?php
namespace Tests\Feature;

use App\Enums\{CategoriaItem, UrgenciaOficina};
use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Area, Solicitud, SolicitudOficina, ItemOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorWorkflowOficinaTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private TipoSolicitud $tipo;
    private Usuario $liderArea;
    private Usuario $rrhh;
    private Usuario $contabilidadLider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->tipo              = TipoSolicitud::where('clave','OFI')->firstOrFail();
        $this->liderArea         = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $this->rrhh              = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
    }

    private function crearSolicitudOficina(): Solicitud
    {
        $cabecera = SolicitudOficina::create([
            'beneficiario' => $this->liderArea->name,
            'urgencia'        => UrgenciaOficina::Media,
            'justificacion'   => 'Necesitamos material de oficina.',
        ]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id,
            'nombre'               => 'Mouse USB',
            'categoria'            => CategoriaItem::Producto,
            'cantidad'             => 2,
            'costo_estimado'       => 35000,
            'subtotal'             => 70000,
        ]);
        $area = Area::first();
        return Solicitud::create([
            'tipo_solicitud_id'  => $this->tipo->id,
            'solicitante_id'     => $this->liderArea->id,
            'area_id'            => $area->id,
            'solicitable_type'   => SolicitudOficina::class,
            'solicitable_id'     => $cabecera->id,
            'estado'             => 'borrador',
            'radicado'           => Solicitud::generarRadicado($this->tipo),
        ]);
    }

    public function test_flujo_completo_oficina(): void
    {
        $solicitud = $this->crearSolicitudOficina();
        $this->assertEquals('borrador', $solicitud->estado);

        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->rrhh);
        $this->assertEquals('verificada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->contabilidadLider);
        $this->assertEquals('aprobada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'pagar', $this->contabilidadLider, null, [
            'valor_pagado'  => 70000,
            'fecha_pago'    => now()->toDateString(),
            'comprobante'   => 'COMP-001',
        ]);
        $this->assertEquals('pagada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contabilidadLider);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);

        $this->assertDatabaseCount('transiciones_solicitud', 5);
    }

    public function test_rol_incorrecto_lanza_excepcion(): void
    {
        $solicitud = $this->crearSolicitudOficina();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);

        $this->expectException(TransicionNoPermitidaException::class);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->liderArea);
    }

    public function test_total_se_recalcula_al_agregar_item(): void
    {
        $solicitud = $this->crearSolicitudOficina();
        $cabecera  = $solicitud->solicitable;
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id,
            'nombre'               => 'Teclado',
            'categoria'            => CategoriaItem::Producto,
            'cantidad'             => 1,
            'costo_estimado'       => 50000,
            'subtotal'             => 50000,
        ]);
        $this->assertEquals(120000, $solicitud->fresh()->total);
    }
}
