import { formatearFechaHoraCompleta } from '@/lib/format';

const ICONOS_ACCION = {
    enviar:'→', verificar:'✓', aprobar:'✓', devolver:'↩', rechazar:'✗',
    pagar:'$', liquidar:'$', cerrar:'■',
};

export default function LineaTiempo({ transiciones }) {
    if (!transiciones?.length) {
        return <p className="text-sm text-slate-400 py-4 text-center">Sin movimientos aún.</p>;
    }

    return (
        <ol className="relative border-l border-slate-200 ml-3 space-y-6">
            {transiciones.map((t) => (
                <li key={t.id} className="ml-6">
                    <span className="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold ring-4 ring-white">
                        {ICONOS_ACCION[t.accion] ?? '·'}
                    </span>
                    <div className="flex items-center gap-2 mb-1">
                        <span className="text-sm font-semibold text-slate-800 capitalize">{t.accion}</span>
                        <span className="text-xs text-slate-400">por {t.usuario.name}</span>
                        <span className="text-xs text-slate-400 ml-auto">{formatearFechaHoraCompleta(t.created_at)}</span>
                    </div>
                    <p className="text-xs text-slate-500">
                        {t.estado_origen ?? '—'} → {t.estado_destino}
                    </p>
                    {t.comentario && (
                        <p className="mt-1 text-xs text-slate-600 bg-slate-50 rounded px-2 py-1 border border-slate-100">
                            {t.comentario}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
