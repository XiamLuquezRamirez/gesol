const COLORES = {
    borrador:       'bg-slate-100 text-slate-600 border-slate-200',
    enviada:        'bg-blue-50 text-blue-700 border-blue-200',
    verificada:     'bg-indigo-50 text-indigo-700 border-indigo-200',
    aprobada:       'bg-emerald-50 text-emerald-700 border-emerald-200',
    pendiente_cierre: 'bg-amber-50 text-amber-700 border-amber-200',
    aprobada_monto: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    pagada:         'bg-teal-50 text-teal-700 border-teal-200',
    liquidada:      'bg-teal-50 text-teal-700 border-teal-200',
    revisada:       'bg-indigo-50 text-indigo-700 border-indigo-200',
    en_gerencia:    'bg-amber-50 text-amber-700 border-amber-200',
    cerrada:        'bg-slate-100 text-slate-500 border-slate-200',
    rechazada:      'bg-red-50 text-red-700 border-red-200',
};

const ETIQUETAS = {
    borrador:'Borrador', enviada:'Enviada', verificada:'Verificada',
    aprobada:'En gerencia · pendiente por pagar', aprobada_monto:'Monto aprobado', pagada:'Pagada',
    pendiente_cierre:'Pendiente por cerrar',
    liquidada:'Liquidada', revisada:'En revisión',
    en_gerencia:'En gerencia · pendiente de cierre',
    cerrada:'Cerrada', rechazada:'Rechazada',
};

// Etiquetas cortas para mostrar el estado en flujos como el historial
// ("Revisada → En gerencia"), sin las coletillas descriptivas del badge.
const ETIQUETAS_CORTAS = {
    aprobada:'En gerencia', pendiente_cierre:'Pendiente por cerrar',
    en_gerencia:'En gerencia', revisada:'Revisada',
};

/**
 * Nombre legible de un estado (nunca el identificador interno con guion bajo).
 * `corta` da la versión sin coletillas, útil para el historial de movimientos.
 * Cualquier estado no mapeado se convierte de snake_case a texto capitalizado.
 */
export function etiquetaEstado(estado, { corta = false } = {}) {
    if (!estado) return '—';
    if (corta && ETIQUETAS_CORTAS[estado]) return ETIQUETAS_CORTAS[estado];
    if (ETIQUETAS[estado]) return ETIQUETAS[estado];
    const texto = String(estado).replace(/_/g, ' ');
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

export default function BadgeEstado({ estado }) {
    const clase = COLORES[estado] ?? 'bg-slate-100 text-slate-600 border-slate-200';
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${clase}`}>
            {ETIQUETAS[estado] ?? estado}
        </span>
    );
}
