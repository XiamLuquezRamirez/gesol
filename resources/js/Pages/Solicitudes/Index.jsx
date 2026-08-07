import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import { formatearMoneda, formatearFecha } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';

export default function Index({ solicitudes, filtros }) {
    const tab = filtros?.tab ?? 'mias';
    const cambiarTab = (nuevoTab) => {
        router.get(route('solicitudes.index'), { tab: nuevoTab }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout title="Solicitudes">
            <Head title="Solicitudes" />
            <div className="flex-1 flex flex-col p-6 w-full">
                {/* Tabs */}
                <div className="flex gap-1 mb-6 border-b border-slate-200">
                    {[
                        { key: 'mias',              label: 'Mis solicitudes' },
                        { key: 'pendientes',        label: 'Pendientes de acción' },
                        { key: 'pendientes_cierre', label: 'Pendientes por cerrar' },
                        { key: 'revisadas',         label: 'Revisadas' },
                    ].map(({ key, label }) => (
                        <button
                            key={key}
                            onClick={() => cambiarTab(key)}
                            className={[
                                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                                tab === key
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700',
                            ].join(' ')}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                

                {/* Lista */}
                {solicitudes.data.length === 0 ? (
                    <div className="text-center py-16 text-slate-400">
                        <p className="text-sm">
                            {tab === 'revisadas'
                                ? 'Aún no has revisado ninguna solicitud.'
                                : tab === 'pendientes'
                                    ? 'No tienes solicitudes pendientes de acción.'
                                    : tab === 'pendientes_cierre'
                                        ? 'No hay solicitudes de oficina pendientes por cerrar.'
                                        : 'No hay solicitudes para mostrar.'}
                        </p>
                        {tab === 'mias' && (
                            <div className="flex gap-3 justify-center mt-4">
                                <Link href={route('oficina.crear')} className="text-sm text-indigo-600 hover:underline">
                                    Nueva solicitud de oficina
                                </Link>
                                <Link href={route('viaticos.crear')} className="text-sm text-indigo-600 hover:underline">
                                    Nueva solicitud de viáticos
                                </Link>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="space-y-2">
                        {solicitudes.data.map((s) => (
                            <Link
                                key={s.id}
                                href={route('solicitudes.show', s.id)}
                                className="block bg-white rounded-xl border border-slate-200 px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition-all"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <span className="text-xs font-mono text-slate-400">{s.radicado}</span>
                                            <BadgeEstado estado={s.estado} />
                                        </div>
                                        <p className="text-sm font-medium text-slate-800 truncate">
                                            {s.tipo.nombre}
                                        </p>
                                        <p className="text-xs text-slate-500 mt-0.5">
                                            {s.solicitante.name} · {formatearFecha(s.created_at)}
                                        </p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <p className="text-sm font-semibold text-slate-800">{formatearMoneda(s.total)}</p>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
