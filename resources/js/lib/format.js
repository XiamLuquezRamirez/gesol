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
