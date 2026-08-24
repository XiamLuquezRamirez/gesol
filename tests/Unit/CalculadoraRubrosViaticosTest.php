<?php

namespace Tests\Unit;

use App\Services\CalculadoraRubrosViaticos;
use PHPUnit\Framework\TestCase;

class CalculadoraRubrosViaticosTest extends TestCase
{
    private CalculadoraRubrosViaticos $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculadoraRubrosViaticos();
    }

    public function test_dias_comision_inclusivo(): void
    {
        $this->assertSame(1, $this->calc->diasComision('2026-01-10', '2026-01-10'));
        $this->assertSame(3, $this->calc->diasComision('2026-01-10', '2026-01-12'));
    }

    public function test_dias_comision_sin_fechas_es_uno(): void
    {
        $this->assertSame(1, $this->calc->diasComision(null, '2026-01-12'));
        $this->assertSame(1, $this->calc->diasComision('2026-01-10', null));
    }

    public function test_mismo_dia_solo_comidas_en_ventana(): void
    {
        // Salida 10:00, regreso 15:00 => almuerzo (14:00) si; desayuno (09:00) no; cena (18:00) no.
        $c = $this->calc->conteoComidas('2026-01-10', '2026-01-10', '10:00', '15:00');
        $this->assertSame(0, $c['desayuno']);
        $this->assertSame(1, $c['almuerzo']);
        $this->assertSame(0, $c['cena']);
        $this->assertSame(1, $c['merienda']); // hubo alguna comida ese dia
    }

    public function test_cena_aplica_si_sigue_despues_de_las_18(): void
    {
        // Un dia, regreso 19:00 => cena cuenta (hora tope 18:00 <= 19:00).
        $c = $this->calc->conteoComidas('2026-01-10', '2026-01-10', '08:00', '19:00');
        $this->assertSame(1, $c['cena']);
    }

    public function test_merienda_una_por_dia_con_comida(): void
    {
        // 3 dias completos sin horas => cada dia tiene comidas => 3 meriendas.
        $c = $this->calc->conteoComidas('2026-01-10', '2026-01-12', null, null);
        $this->assertSame(3, $c['merienda']);
    }

    public function test_dias_de_rubro_gasolina_usa_dias_comision(): void
    {
        $this->assertSame(3, $this->calc->diasDeRubro('gasolina', '2026-01-10', '2026-01-12', null, null));
        $this->assertSame(3, $this->calc->diasDeRubro('transporte', '2026-01-10', '2026-01-12', null, null));
    }

    public function test_dias_de_rubro_comida_usa_conteo(): void
    {
        // Mismo dia 10:00-15:00: desayuno 0, almuerzo 1.
        $this->assertSame(0, $this->calc->diasDeRubro('desayuno', '2026-01-10', '2026-01-10', '10:00', '15:00'));
        $this->assertSame(1, $this->calc->diasDeRubro('almuerzo', '2026-01-10', '2026-01-10', '10:00', '15:00'));
    }

    public function test_calcular_delta_extiende_suma(): void
    {
        // Antes: 1 dia (10 al 10, 08:00-15:00). Despues: 2 dias (10 al 11, 08:00-19:00).
        //
        // Antes (1 dia, minSalida=480, minRegreso=900):
        //   desayuno(540): 540>=480 && 540<=900 -> 1
        //   almuerzo(840): 840>=480 && 840<=900 -> 1
        //   cena(1080):    1080>=480 && 1080<=900 -> 0
        //   merienda: 1 | gasolina/transporte: diasComision=1
        // Despues (2 dias, minSalida=480, minRegreso=1140):
        //   dia0 (primero): desayuno/almuerzo/cena todas h>=480 -> presentes
        //   dia1 (ultimo):  desayuno/almuerzo/cena todas h<=1140 -> presentes
        //   desayuno=2, almuerzo=2, cena=2, merienda=2 | gasolina/transporte: diasComision=2
        $antes   = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-10', 'hora_regreso' => '15:00'];
        $despues = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-11', 'hora_regreso' => '19:00'];
        $delta = $this->calc->calcularDelta($antes, $despues);

        $this->assertSame(1, $delta['gasolina']);   // 2 - 1
        $this->assertSame(1, $delta['transporte']); // 2 - 1
        $this->assertSame(1, $delta['desayuno']);   // 2 - 1
        $this->assertSame(1, $delta['almuerzo']);   // 2 - 1
        $this->assertSame(2, $delta['cena']);       // 2 - 0
        $this->assertSame(1, $delta['merienda']);   // 2 - 1
    }

    public function test_calcular_delta_recorta_resta(): void
    {
        // Antes: 3 dias (10 al 12). Despues: 2 dias (10 al 11).
        $antes   = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-12', 'hora_regreso' => '19:00'];
        $despues = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-11', 'hora_regreso' => '19:00'];
        $delta = $this->calc->calcularDelta($antes, $despues);
        $this->assertSame(-1, $delta['gasolina']);   // 2 - 3
        $this->assertSame(-1, $delta['transporte']); // 2 - 3
    }

    public function test_calcular_delta_sin_cambio_vacio(): void
    {
        $mismo = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-12', 'hora_regreso' => '19:00'];
        $this->assertSame([], $this->calc->calcularDelta($mismo, $mismo));
    }
}
