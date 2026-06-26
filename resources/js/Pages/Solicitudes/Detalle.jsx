import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import LineaTiempo from '@/Components/LineaTiempo';
import ModalAccion from '@/Components/ModalAccion';
import { formatearMoneda, formatearFecha } from '@/lib/format';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';

function SeccionCard({ titulo, children }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">{titulo}</h3>
            {children}
        </div>
    );
}

function Campo({ label, valor }) {
    return (
        <div>
            <dt className="text-xs text-slate-500 mb-0.5">{label}</dt>
            <dd className="text-sm text-slate-800 font-medium">{valor ?? '—'}</dd>
        </div>
    );
}

function DetalleOficina({ solicitable }) {
    if (!solicitable) return null;
    return (
        <>
            <dl className="grid grid-cols-2 gap-4 mb-5">
                <Campo label="Beneficiario" valor={solicitable.beneficiario?.name} />
                <Campo label="Urgencia" valor={solicitable.urgencia} />
                <Campo label="Justificación" valor={solicitable.justificacion} />
                <Campo label="Total" valor={formatearMoneda(solicitable.total)} />
            </dl>
            {solicitable.items?.length > 0 && (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs text-slate-500 border-b border-slate-100">
                            <th className="pb-2 font-medium">Ítem</th>
                            <th className="pb-2 font-medium">Cant.</th>
                            <th className="pb-2 font-medium text-right">Costo unit.</th>
                            <th className="pb-2 font-medium text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {solicitable.items.map((item, i) => (
                            <tr key={i} className="text-slate-700">
                                <td className="py-2">{item.nombre}</td>
                                <td className="py-2">{item.cantidad}</td>
                                <td className="py-2 text-right">{formatearMoneda(item.costo_estimado)}</td>
                                <td className="py-2 text-right font-medium">{formatearMoneda(item.subtotal)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </>
    );
}

function DetalleViaticos({ solicitable }) {
    if (!solicitable) return null;
    return (
        <dl className="grid grid-cols-2 gap-4">
            <Campo label="Comisión" valor={solicitable.nombre_comision} />
            <Campo label="Destino" valor={solicitable.municipio_destino} />
            <Campo label="Salida" valor={formatearFecha(solicitable.fecha_salida)} />
            <Campo label="Regreso" valor={formatearFecha(solicitable.fecha_regreso)} />
            <Campo label="Total" valor={formatearMoneda(solicitable.total)} />
        </dl>
    );
}

export default function Detalle({ solicitud, acciones }) {
    const [accionActiva, setAccionActiva] = useState(null);
    const { props } = usePage();
    const flash = props.flash ?? {};

    const esOficina   = solicitud.tipo?.clave === 'OFI';
    const esViaticos  = solicitud.tipo?.clave === 'VIA';

    return (
        <AppLayout title={solicitud.radicado}>
            <div className="p-6 max-w-5xl mx-auto w-full space-y-5">
                {flash.success && (
                    <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-lg px-4 py-2.5">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-2.5">
                        {flash.error}
                    </div>
                )}

                {/* Cabecera */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <span className="font-mono text-base font-semibold text-slate-700">{solicitud.radicado}</span>
                            <BadgeEstado estado={solicitud.estado} />
                        </div>
                        <p className="text-sm text-slate-500">
                            {solicitud.tipo?.nombre} · {solicitud.solicitante?.name} · {formatearFecha(solicitud.created_at)}
                        </p>
                    </div>
                    {acciones.length > 0 && (
                        <div className="flex gap-2 flex-wrap justify-end">
                            {acciones.map((a) => (
                                <button
                                    key={a.accion}
                                    onClick={() => setAccionActiva(a)}
                                    className="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors capitalize"
                                >
                                    {a.accion}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Detalle del proceso */}
                <SeccionCard titulo="Detalle">
                    {esOficina  && <DetalleOficina  solicitable={solicitud.solicitable} />}
                    {esViaticos && <DetalleViaticos solicitable={solicitud.solicitable} />}
                </SeccionCard>

                {/* Línea de tiempo */}
                <SeccionCard titulo="Historial de movimientos">
                    <LineaTiempo transiciones={solicitud.transiciones} />
                </SeccionCard>
            </div>

            <ModalAccion
                solicitudId={solicitud.id}
                accion={accionActiva}
                onClose={() => setAccionActiva(null)}
            />
        </AppLayout>
    );
}
