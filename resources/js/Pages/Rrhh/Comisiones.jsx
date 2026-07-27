import AppLayout from '@/Layouts/AppLayout';
import { formatearFecha } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Comisiones({ comisionados, filtros }) {
    const [desde, setDesde] = useState(filtros?.desde ?? '');
    const [hasta, setHasta] = useState(filtros?.hasta ?? '');

    const aplicar = (e) => {
        e?.preventDefault();
        router.get(route('rrhh.comisiones'),
            { desde: desde || undefined, hasta: hasta || undefined },
            { preserveState: true, replace: true });
    };

    const limpiar = () => {
        setDesde('');
        setHasta('');
        router.get(route('rrhh.comisiones'), {}, { preserveState: true, replace: true });
    };

    const hayFiltro = filtros?.desde || filtros?.hasta;

    return (
        <AppLayout title="Comisiones RR. HH.">
            <Head title="Comisiones RR. HH." />
            <div className="p-6 w-full space-y-5">

                {/* Cabecera */}
                <div>
                    <h2 className="text-lg font-semibold text-slate-800">Personal en comisión</h2>
                    <p className="text-sm text-slate-500 mt-0.5">
                        Empleados que estuvieron por fuera en comisiones cerradas. Filtre por rango de fechas.
                    </p>
                </div>

                {/* Filtros */}
                <form onSubmit={aplicar} className="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-end gap-4">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Desde</label>
                        <input
                            type="date"
                            value={desde}
                            onChange={(e) => setDesde(e.target.value)}
                            className="rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
                        <input
                            type="date"
                            value={hasta}
                            onChange={(e) => setHasta(e.target.value)}
                            className="rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
                        />
                    </div>
                    <button
                        type="submit"
                        className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors"
                    >
                        Aplicar filtro
                    </button>
                    {hayFiltro && (
                        <button
                            type="button"
                            onClick={limpiar}
                            className="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            Limpiar
                        </button>
                    )}
                </form>

                {/* Tabla */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    {comisionados.length === 0 ? (
                        <p className="text-sm text-slate-400 text-center py-10">
                            {hayFiltro
                                ? 'No hay comisionados en el rango seleccionado.'
                                : 'No hay comisionados en comisiones cerradas.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Empleado</th>
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Identificación</th>
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Comisión</th>
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Destino</th>
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Salida</th>
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Regreso</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {comisionados.map((c) => (
                                        <tr key={c.id} className="hover:bg-slate-50/50">
                                            <td className="px-4 py-3 font-medium text-slate-700">{c.empleado || '—'}</td>
                                            <td className="px-4 py-3 font-mono text-slate-500">{c.identificacion ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600">{c.comision ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600">{c.destino ?? '—'}</td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {formatearFecha(c.fecha_salida)}{c.hora_salida ? ` · ${c.hora_salida}` : ''}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {formatearFecha(c.fecha_regreso)}{c.hora_regreso ? ` · ${c.hora_regreso}` : ''}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}
