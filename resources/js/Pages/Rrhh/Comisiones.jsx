import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import BadgeEstado from '@/Components/BadgeEstado';
import { formatearFecha, formatearMoneda } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const etiquetaRubro = (r) =>
    r ? r.charAt(0).toUpperCase() + r.slice(1).replace(/[_-]/g, ' ') : '—';

const ETIQUETAS_PAGO = { efectivo: 'Efectivo', transferencia: 'Transferencia' };

export default function Comisiones({ comisionados, filtros }) {
    const [desde, setDesde] = useState(filtros?.desde ?? '');
    const [hasta, setHasta] = useState(filtros?.hasta ?? '');
    const [nombre, setNombre] = useState(filtros?.nombre ?? '');
    const [comision, setComision] = useState(filtros?.comision ?? '');
    const [detalle, setDetalle] = useState(null); // comisionado seleccionado para ver rubros
    const aplicar = (e) => {
        e?.preventDefault();
        router.get(route('rrhh.comisiones'),
            {
                desde: desde || undefined,
                hasta: hasta || undefined,
                nombre: nombre || undefined,
                comision: comision || undefined,
            },
            { preserveState: true, replace: true });
    };

    const limpiar = () => {
        setDesde('');
        setHasta('');
        setNombre('');
        setComision('');
        router.get(route('rrhh.comisiones'), {}, { preserveState: true, replace: true });
    };

    const hayFiltro = filtros?.desde || filtros?.hasta || filtros?.nombre || filtros?.comision;

    return (
        <AppLayout title="Comisiones RR. HH.">
            <Head title="Comisiones RR. HH." />
            <div className="p-6 w-full space-y-5">

                {/* Cabecera */}
                <div>
                    <h2 className="text-lg font-semibold text-slate-800">Personal en comisión</h2>
                    <p className="text-sm text-slate-500 mt-0.5">
                        Empleados que están o estuvieron por fuera en comisión. Filtre por empleado, comisión o rango de fechas.
                    </p>
                </div>

                {/* Filtros */}
                <form onSubmit={aplicar} className="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap items-end gap-4">
                    
                    {/* Filtro por nombre del empleado */}
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Nombre del empleado</label>
                        <input
                            type="text"
                            value={nombre}
                            onChange={(e) => setNombre(e.target.value)}
                            className="rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
                        />
                    </div>

                    {/* Filtro por comisión */}
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Comisión</label>
                        <input
                            type="text"
                            value={comision}
                            onChange={(e) => setComision(e.target.value)}
                            placeholder="Nombre de la comisión"
                            className="rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
                        />
                    </div>

                    {/* Filtro por rango de fechas */}
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
                                ? 'No hay comisionados que coincidan con el filtro.'
                                : 'No hay comisionados en comisión reportada.'}
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
                                        <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Estado</th>
                                        <th className="text-right text-xs font-semibold text-slate-500 px-4 py-3">Rubros</th>
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
                                            <td className="px-4 py-3">
                                                {c.estado ? <BadgeEstado estado={c.estado} /> : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetalle(c)}
                                                    className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-lg text-indigo-600 border border-indigo-200 hover:bg-indigo-50 transition-colors"
                                                >
                                                    Ver rubros
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

            </div>

            {/* Modal: rubros asignados al empleado */}
            <Modal show={detalle !== null} onClose={() => setDetalle(null)} maxWidth="lg">
                {detalle && (
                    <div className="p-6">
                        <div className="flex items-start justify-between mb-1">
                            <h3 className="text-base font-semibold text-slate-800">Rubros asignados</h3>
                            <button
                                type="button"
                                onClick={() => setDetalle(null)}
                                className="text-slate-400 hover:text-slate-600 text-xl leading-none"
                                aria-label="Cerrar"
                            >
                                ×
                            </button>
                        </div>
                        <p className="text-sm text-slate-500 mb-4">
                            {detalle.empleado} · {detalle.comision ?? '—'}
                            {detalle.tipo_pago ? ` · ${ETIQUETAS_PAGO[detalle.tipo_pago] ?? detalle.tipo_pago}` : ''}
                        </p>

                        {(!detalle.rubros || detalle.rubros.length === 0) ? (
                            <p className="text-sm text-slate-400 text-center py-6">
                                Este empleado no tiene rubros asignados.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-lg border border-slate-100">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 border-b border-slate-100">
                                        <tr className="text-left text-xs text-slate-500">
                                            <th className="px-3 py-2 font-medium">Rubro</th>
                                            <th className="px-3 py-2 font-medium text-right">Valor unit.</th>
                                            <th className="px-3 py-2 font-medium text-center">Días</th>
                                            <th className="px-3 py-2 font-medium text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {detalle.rubros.map((r, i) => (
                                            <tr key={i} className="text-slate-700">
                                                <td className="px-3 py-2.5">{etiquetaRubro(r.rubro)}</td>
                                                <td className="px-3 py-2.5 text-right">{formatearMoneda(r.valor_unitario)}</td>
                                                <td className="px-3 py-2.5 text-center">{r.dias}</td>
                                                <td className="px-3 py-2.5 text-right font-medium">{formatearMoneda(r.subtotal)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-slate-50 border-t border-slate-100">
                                        <tr>
                                            <td colSpan={3} className="px-3 py-2.5 text-right text-xs font-semibold text-slate-500">Total</td>
                                            <td className="px-3 py-2.5 text-right font-semibold text-slate-800">{formatearMoneda(detalle.total)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}

                        <div className="flex justify-end mt-5">
                            <button
                                type="button"
                                onClick={() => setDetalle(null)}
                                className="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}
