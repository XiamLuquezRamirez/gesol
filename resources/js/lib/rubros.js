// Cálculo de rubros de viáticos por defecto según las fechas Y horas de la comisión.
//
// Reglas:
// - Cada comida tiene una hora de referencia: desayuno 09:00, almuerzo 14:00, cena 18:00.
//   La cena aplica si sigue en comisión después de las 6 de la tarde (18:00).
// - Una comida cuenta un día dado si su hora cae dentro de la ventana de presencia:
//     · primer día  → desde hora_salida hasta fin del día  (hora_comida >= hora_salida)
//     · último día  → desde inicio hasta hora_regreso       (hora_comida <= hora_regreso)
//     · días intermedios → todas
//     · mismo día   → hora_salida <= hora_comida <= hora_regreso
// - Merienda: 1 por día si hay alguna comida presente ese día.

// Horas tope de cada comida, en minutos desde medianoche.
const HORA_COMIDA = {
    desayuno: 9 * 60,   // 09:00
    almuerzo: 14 * 60,  // 14:00
    cena:     18 * 60,  // 18:00 — la cena aplica si sigue en comisión después de las 6 p. m.
};

// "HH:MM" -> minutos. Sin hora válida, devuelve el default indicado (inicio/fin del día).
function aMinutos(hora, porDefecto) {
    if (!hora) return porDefecto;
    const m = String(hora).match(/^(\d{1,2}):(\d{2})/);
    if (!m) return porDefecto;
    return parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
}

function fechaSolo(f) {
    return new Date(String(f).substring(0, 10) + 'T00:00:00');
}

// Días de comisión, contando de forma inclusiva (salida y regreso cuentan).
export function diasComision(fechaSalida, fechaRegreso) {
    if (!fechaSalida || !fechaRegreso) return 1;
    const diff = Math.round((fechaSolo(fechaRegreso) - fechaSolo(fechaSalida)) / 86400000) + 1;
    return Math.max(1, diff);
}

// ¿La comida `nombre` está presente el día `indice` (0 = primer día, n-1 = último)?
// Ajusta los bordes según hora de salida/regreso.
function comidaPresente(nombre, indice, totalDias, minSalida, minRegreso) {
    const h = HORA_COMIDA[nombre];
    const esPrimero = indice === 0;
    const esUltimo = indice === totalDias - 1;

    if (totalDias === 1) {
        // Mismo día: entre salida y regreso.
        return h >= minSalida && h <= minRegreso;
    }
    if (esPrimero) return h >= minSalida;
    if (esUltimo) return h <= minRegreso;
    return true; // día intermedio
}

// Cuenta cuántas veces aplica cada comida a lo largo de toda la comisión,
// afinando los días de borde con las horas.
// Devuelve { desayuno, almuerzo, cena, merienda }.
export function conteoComidas(fechaSalida, fechaRegreso, horaSalida, horaRegreso) {
    const dias = diasComision(fechaSalida, fechaRegreso);
    const minSalida = aMinutos(horaSalida, 0);          // sin hora → inicio del día
    const minRegreso = aMinutos(horaRegreso, 24 * 60);  // sin hora → fin del día

    const conteo = { desayuno: 0, almuerzo: 0, cena: 0, merienda: 0 };

    for (let i = 0; i < dias; i++) {
        const desayuno = comidaPresente('desayuno', i, dias, minSalida, minRegreso);
        const almuerzo = comidaPresente('almuerzo', i, dias, minSalida, minRegreso);
        const cena = comidaPresente('cena', i, dias, minSalida, minRegreso);

        if (desayuno) conteo.desayuno += 1;
        if (almuerzo) conteo.almuerzo += 1;
        if (cena) conteo.cena += 1;

        // Merienda: 1 por día si hay alguna comida presente.
        if (desayuno || almuerzo || cena) conteo.merienda += 1;
    }

    return conteo;
}

// Orden en que se muestran los rubros por defecto.
const ORDEN_RUBROS = ['desayuno', 'almuerzo', 'merienda', 'cena'];

// Días que corresponden a un rubro concreto según las fechas/horas del viajero.
// Rubros de comida usan el conteo por franjas; los demás (p. ej. gasolina) usan
// los días de comisión. Se usa para recalcular la liquidación tras un ajuste de fechas.
export function diasDeRubro(rubro, fechaSalida, fechaRegreso, horaSalida, horaRegreso) {
    const comidas = conteoComidas(fechaSalida, fechaRegreso, horaSalida, horaRegreso);
    if (rubro in comidas) return comidas[rubro];
    return diasComision(fechaSalida, fechaRegreso);
}

// Genera las asignaciones por defecto de un viajero según sus fechas y horas.
// `rubrosDisponibles` son las claves de tarifa existentes (para no inventar rubros).
// `tarifas` mapea rubro -> { valor_sugerido }.
export function rubrosPorDefecto(fechaSalida, fechaRegreso, horaSalida, horaRegreso, rubrosDisponibles, tarifas) {
    const conteo = conteoComidas(fechaSalida, fechaRegreso, horaSalida, horaRegreso);
    return ORDEN_RUBROS
        .filter((r) => rubrosDisponibles.includes(r))
        .map((rubro) => ({
            rubro,
            valor_unitario: tarifas?.[rubro]?.valor_sugerido ?? 0,
            dias: conteo[rubro] ?? 0,
        }))
        .filter((a) => a.dias > 0); // omitir rubros que no aplican (cantidad 0)
}
