export function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', minimumFractionDigits: 0,
    }).format(valor ?? 0);
}

export function formatearFecha(fechaStr) {
    if (!fechaStr) return '—';
    return new Date(fechaStr).toLocaleDateString('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}

/**
 * Convierte una hora guardada en 24h ('HH:MM') a 12h con a. m./p. m. legible.
 * Ej. '08:00' -> '8:00 a. m.', '17:00' -> '5:00 p. m.'. Devuelve '' si no hay hora.
 */
export function formatearHora(horaStr) {
    if (!horaStr) return '';
    const [h, m] = String(horaStr).split(':');
    const hora = Number(h);
    const min = Number(m);
    if (Number.isNaN(hora) || Number.isNaN(min)) return String(horaStr);
    const d = new Date(2000, 0, 1, hora, min);
    return d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
}

/**
 * Fecha + hora legibles: '20 ago 2026 · 5:00 p. m.'. Sin hora muestra solo la
 * fecha; sin fecha devuelve '—'. Unifica las copias que había por página.
 */
export function formatFechaHora(fechaStr, horaStr) {
    if (!fechaStr) return '—';
    const fecha = new Date(String(fechaStr).substring(0, 10) + 'T00:00:00').toLocaleDateString('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
    const hora = formatearHora(horaStr);
    return hora ? `${fecha} · ${hora}` : fecha;
}

/**
 * Formatea un datetime tipo 'Y-m-d H:i' ('2026-08-20 17:00') a legible con
 * a. m./p. m.: '20 ago 2026 · 5:00 p. m.'. Usado en el historial de movimientos.
 */
export function formatearFechaHoraCompleta(fechaHoraStr) {
    if (!fechaHoraStr) return '—';
    const [fecha, hora] = String(fechaHoraStr).trim().split(' ');
    return formatFechaHora(fecha, hora);
}
