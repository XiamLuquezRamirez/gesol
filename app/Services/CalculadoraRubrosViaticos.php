<?php

namespace App\Services;

/**
 * Port server-side de resources/js/lib/rubros.js.
 *
 * Calcula los dias que corresponden a cada rubro de viaticos segun las fechas y
 * horas de la comision de un viajero, y el delta (diferencia) entre dos snapshots
 * (antes/despues) de esas fechas/horas. Fuente de verdad para recalcular la
 * liquidacion tras un ajuste de fechas.
 *
 * Reglas (identicas a rubros.js):
 * - Cada comida tiene hora tope: desayuno 09:00, almuerzo 14:00, cena 18:00.
 *   La cena aplica si sigue en comision despues de las 18:00.
 * - Una comida cuenta un dia si su hora cae dentro de la ventana de presencia:
 *     primer dia -> hora_comida >= hora_salida
 *     ultimo dia -> hora_comida <= hora_regreso
 *     dias intermedios -> siempre
 *     mismo dia -> hora_salida <= hora_comida <= hora_regreso
 * - Merienda: 1 por dia si hay alguna comida presente ese dia.
 */
class CalculadoraRubrosViaticos
{
    // Horas tope de cada comida, en minutos desde medianoche (identico a rubros.js).
    private const HORA_COMIDA = [
        'desayuno' => 9 * 60,   // 09:00
        'almuerzo' => 14 * 60,  // 14:00
        'cena'     => 18 * 60,  // 18:00 — la cena aplica si sigue en comision despues de las 6 p. m.
    ];

    /** "HH:MM" -> minutos. Sin hora valida devuelve el default (inicio/fin del dia). */
    private function aMinutos(?string $hora, int $porDefecto): int
    {
        if (! $hora) {
            return $porDefecto;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
            return $porDefecto;
        }

        return ((int) $m[1]) * 60 + ((int) $m[2]);
    }

    private function fechaSolo(string $f): \DateTimeImmutable
    {
        return new \DateTimeImmutable(substr($f, 0, 10).' 00:00:00');
    }

    /** Dias de comision, inclusivo (salida y regreso cuentan), minimo 1. */
    public function diasComision(?string $fechaSalida, ?string $fechaRegreso): int
    {
        if (! $fechaSalida || ! $fechaRegreso) {
            return 1;
        }
        $dif = $this->fechaSolo($fechaSalida)->diff($this->fechaSolo($fechaRegreso))->days;

        return max(1, $dif + 1);
    }

    /** ¿La comida esta presente el dia $indice (0 = primero)? Afina bordes con horas. */
    private function comidaPresente(string $nombre, int $indice, int $totalDias, int $minSalida, int $minRegreso): bool
    {
        $h = self::HORA_COMIDA[$nombre];
        $esPrimero = $indice === 0;
        $esUltimo = $indice === $totalDias - 1;

        if ($totalDias === 1) {
            return $h >= $minSalida && $h <= $minRegreso;
        }
        if ($esPrimero) {
            return $h >= $minSalida;
        }
        if ($esUltimo) {
            return $h <= $minRegreso;
        }

        return true; // dia intermedio
    }

    /** Cuenta cada comida a lo largo de la comision. Devuelve [desayuno,almuerzo,cena,merienda]. */
    public function conteoComidas(?string $fechaSalida, ?string $fechaRegreso, ?string $horaSalida, ?string $horaRegreso): array
    {
        $dias = $this->diasComision($fechaSalida, $fechaRegreso);
        $minSalida = $this->aMinutos($horaSalida, 0);          // sin hora -> inicio del dia
        $minRegreso = $this->aMinutos($horaRegreso, 24 * 60);  // sin hora -> fin del dia

        $c = ['desayuno' => 0, 'almuerzo' => 0, 'cena' => 0, 'merienda' => 0];
        for ($i = 0; $i < $dias; $i++) {
            $d = $this->comidaPresente('desayuno', $i, $dias, $minSalida, $minRegreso);
            $a = $this->comidaPresente('almuerzo', $i, $dias, $minSalida, $minRegreso);
            $ce = $this->comidaPresente('cena', $i, $dias, $minSalida, $minRegreso);
            if ($d) {
                $c['desayuno'] += 1;
            }
            if ($a) {
                $c['almuerzo'] += 1;
            }
            if ($ce) {
                $c['cena'] += 1;
            }
            // Merienda: 1 por dia si hay alguna comida presente.
            if ($d || $a || $ce) {
                $c['merienda'] += 1;
            }
        }

        return $c;
    }

    /** Dias que corresponden a un rubro segun fechas/horas. Comidas usan conteo; resto usa dias. */
    public function diasDeRubro(string $rubro, ?string $fechaSalida, ?string $fechaRegreso, ?string $horaSalida, ?string $horaRegreso): int
    {
        $comidas = $this->conteoComidas($fechaSalida, $fechaRegreso, $horaSalida, $horaRegreso);
        if (array_key_exists($rubro, $comidas)) {
            return $comidas[$rubro];
        }

        return $this->diasComision($fechaSalida, $fechaRegreso);
    }

    /**
     * Delta de dias por rubro entre el snapshot ANTES y DESPUES.
     * Cada snapshot: ['fecha_salida','hora_salida','fecha_regreso','hora_regreso'].
     * Devuelve solo rubros con delta != 0. Puede ser negativo (se recorto la comision).
     */
    public function calcularDelta(array $antes, array $despues): array
    {
        $rubros = ['desayuno', 'almuerzo', 'cena', 'merienda', 'gasolina', 'transporte'];
        $delta = [];
        foreach ($rubros as $rubro) {
            $diasAntes = $this->diasDeRubro(
                $rubro,
                $antes['fecha_salida'] ?? null,
                $antes['fecha_regreso'] ?? null,
                $antes['hora_salida'] ?? null,
                $antes['hora_regreso'] ?? null
            );
            $diasDespues = $this->diasDeRubro(
                $rubro,
                $despues['fecha_salida'] ?? null,
                $despues['fecha_regreso'] ?? null,
                $despues['hora_salida'] ?? null,
                $despues['hora_regreso'] ?? null
            );
            $d = $diasDespues - $diasAntes;
            if ($d !== 0) {
                $delta[$rubro] = $d;
            }
        }

        return $delta;
    }
}
