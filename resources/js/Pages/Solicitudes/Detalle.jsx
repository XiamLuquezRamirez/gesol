import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import LineaTiempo from '@/Components/LineaTiempo';
import ModalAccion from '@/Components/ModalAccion';
import { formatearMoneda, formatearFecha } from '@/lib/format';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { ArrowLeftIcon, ArrowRightIcon, ArrowUturnLeftIcon, CheckCircleIcon, CheckIcon, XCircleIcon, CreditCardIcon, PencilSquareIcon } from '@heroicons/react/24/outline';

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


const COLORES_ACCION = {
    enviar:    'bg-yellow-600 hover:bg-yellow-700',
    devolver:  'bg-orange-600 hover:bg-orange-700',
    verificar: 'bg-blue-600 hover:bg-blue-700',
    aprobar:   'bg-green-600 hover:bg-green-700',
    rechazar:  'bg-red-600 hover:bg-red-700',
    pagar:     'bg-violet-600 hover:bg-violet-700',
    liquidar:  'bg-teal-600 hover:bg-teal-700',
    cerrar:    'bg-slate-600 hover:bg-slate-700',
};

function colorBotonAccion(accion) {
    return COLORES_ACCION[accion] ?? 'bg-indigo-600 hover:bg-indigo-700';
}

function IconoAccion({ accion }) {
    const cls = 'w-4 h-4';
    switch (accion) {
        case 'enviar':
            return <ArrowRightIcon className={cls} />;
        case 'devolver':
            return <ArrowUturnLeftIcon className={cls} />;
        case 'verificar':
        case 'aprobar':
            return <CheckCircleIcon className={cls} />;
        case 'rechazar':
            return <XCircleIcon className={cls} />;
        case 'pagar':
            return <CreditCardIcon className={cls} />;
        case 'liquidar':
        case 'cerrar':
            return <CheckIcon className={cls} />;
        default:
            return null;
    }
}


const ETIQUETAS_URGENCIA = { alta: 'Alta', media: 'Media', baja: 'Baja' };

function DetalleOficina({ solicitable }) {
    if (!solicitable) return null;
    return (
        <>
            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 mb-5">
                <Campo label="Beneficiario" valor={solicitable.beneficiario} />
                <Campo label="Urgencia" valor={ETIQUETAS_URGENCIA[solicitable.urgencia] ?? solicitable.urgencia} />
                <div className="col-span-2">
                    <Campo label="Justificación" valor={solicitable.justificacion} />
                </div>
                <Campo label="Total estimado" valor={formatearMoneda(solicitable.total)} />
            </dl>
            {solicitable.items?.length > 0 && (
                <div className="overflow-x-auto rounded-lg border border-slate-100">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100">
                            <tr className="text-left text-xs text-slate-500">
                                <th className="px-3 py-2 font-medium">Ítem</th>
                                <th className="px-3 py-2 font-medium text-center">Cant.</th>
                                <th className="px-3 py-2 font-medium text-right">Costo unit.</th>
                                <th className="px-3 py-2 font-medium text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {solicitable.items.map((item, i) => (
                                <tr key={i} className="text-slate-700 hover:bg-slate-50">
                                    <td className="px-3 py-2.5">{item.nombre}</td>
                                    <td className="px-3 py-2.5 text-center">{item.cantidad}</td>
                                    <td className="px-3 py-2.5 text-right">{formatearMoneda(item.costo_estimado)}</td>
                                    <td className="px-3 py-2.5 text-right font-medium">{formatearMoneda(item.subtotal)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}

function DetalleViaticos({ solicitable }) {
    if (!solicitable) return null;
    const viajeros = solicitable.viajeros ?? [];

    const formatFechaHora = (fecha, hora) => {
        if (!fecha) return '—';
        const f = new Date(String(fecha).substring(0, 10) + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
        return hora ? `${f} ${hora}` : f;
    };

    return (
        <div className="space-y-5">
            {/* Información general */}
            <dl className="grid grid-cols-2 gap-x-6 gap-y-4">
                <Campo label="Nombre de la comisión" valor={solicitable.nombre_comision} />
                <Campo label="Municipio destino" valor={solicitable.municipio_destino} />
                <Campo label="Total asignado" valor={formatearMoneda(solicitable.total)} />
                {solicitable.observacion && (
                    <div className="col-span-2">
                        <Campo label="Observación general" valor={solicitable.observacion} />
                    </div>
                )}
            </dl>

            {/* Tabla de viajeros */}
            {viajeros.length > 0 && (
                <>
                    <div className="border-t border-slate-100 pt-4">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
                            Viajeros ({viajeros.length})
                        </p>
                        <div className="overflow-x-auto rounded-lg border border-slate-100">
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 border-b border-slate-100">
                                    <tr className="text-left text-xs text-slate-500">
                                        <th className="px-3 py-2 font-medium">Viajero</th>
                                        <th className="px-3 py-2 font-medium">Motivo</th>
                                        <th className="px-3 py-2 font-medium whitespace-nowrap">Salida</th>
                                        <th className="px-3 py-2 font-medium whitespace-nowrap">Regreso</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {viajeros.map((v) => (
                                        <tr key={v.id} className="hover:bg-slate-50">
                                            <td className="px-3 py-2.5 font-medium text-slate-800 whitespace-nowrap">
                                                {v.empleado
                                                    ? `${v.empleado.nombres} ${v.empleado.apellidos}`
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2.5 text-slate-600 max-w-xs">
                                                <p className="truncate" title={v.motivo}>{v.motivo || '—'}</p>
                                            </td>
                                            <td className="px-3 py-2.5 text-slate-600 whitespace-nowrap">{formatFechaHora(v.fecha_salida, v.hora_salida)}</td>
                                            <td className="px-3 py-2.5 text-slate-600 whitespace-nowrap">{formatFechaHora(v.fecha_regreso, v.hora_regreso)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

function AvisoRechazo({ transicion, rutaEditar }) {
    const esRechazo = transicion.accion === 'rechazar';

    return (
        <div className={`rounded-xl border p-4 ${
            esRechazo ? 'bg-red-50 border-red-200' : 'bg-orange-50 border-orange-200'
        }`}>
            <div className="flex items-start gap-3">
                <div className={`shrink-0 mt-0.5 w-8 h-8 rounded-full flex items-center justify-center ${
                    esRechazo ? 'bg-red-100' : 'bg-orange-100'
                }`}>
                    {esRechazo
                        ? <XCircleIcon className="w-5 h-5 text-red-600" />
                        : <ArrowUturnLeftIcon className="w-5 h-5 text-orange-600" />
                    }
                </div>
                <div className="flex-1 min-w-0">
                    <p className={`text-sm font-semibold ${esRechazo ? 'text-red-800' : 'text-orange-800'}`}>
                        {esRechazo ? 'Solicitud rechazada' : 'Solicitud devuelta para corrección'}
                    </p>
                    <p className={`text-xs mt-0.5 ${esRechazo ? 'text-red-500' : 'text-orange-500'}`}>
                        Por {transicion.usuario?.name} · {transicion.created_at}
                    </p>
                    {transicion.comentario ? (
                        <p className={`mt-2 text-sm px-3 py-2 rounded-lg bg-white/70 border ${
                            esRechazo ? 'text-red-700 border-red-100' : 'text-orange-700 border-orange-100'
                        }`}>
                            {transicion.comentario}
                        </p>
                    ) : (
                        <p className="mt-2 text-xs italic text-slate-400">No se indicó una razón.</p>
                    )}
                </div>
            </div>

            {!esRechazo && rutaEditar && (
                <div className="mt-4 pt-4 border-t border-orange-200 flex items-center justify-between gap-3">
                    <p className="text-xs text-orange-600">
                        Corrija los puntos indicados y vuelva a enviar la solicitud.
                    </p>
                    <a
                        href={rutaEditar}
                        className="shrink-0 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-lg transition-colors"
                    >
                        <ArrowUturnLeftIcon className="w-4 h-4" />
                        Editar y corregir
                    </a>
                </div>
            )}
        </div>
    );
}

export default function Detalle({ solicitud, acciones, rutaEditar }) {
    const [accionActiva, setAccionActiva] = useState(null);
    const { props } = usePage();
    const flash = props.flash ?? {};

    const esOficina  = solicitud.tipo?.clave === 'OFI';
    const esViaticos = solicitud.tipo?.clave === 'VIA';

    const transicionesRaw  = solicitud.transiciones;
    const transiciones     = Array.isArray(transicionesRaw)
        ? transicionesRaw
        : (transicionesRaw?.data ?? []);
    const ultimaTransicion = transiciones[transiciones.length - 1];
    const mostrarAviso     = ['rechazar', 'devolver'].includes(ultimaTransicion?.accion);

    return (
        <AppLayout title={solicitud.radicado}>
            <Head title={'Solicitud ' + solicitud.radicado} />
            <div className="flex-1 flex flex-col p-6 w-full space-y-5">
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

                    <div className="flex gap-2 flex-wrap justify-end">
                        <a href={route('solicitudes.index')} className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                            <ArrowLeftIcon className="w-4 h-4" /> Volver
                        </a>
                        {rutaEditar && (
                            <a href={rutaEditar} className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-slate-600 text-white hover:bg-slate-700 transition-colors">
                                <PencilSquareIcon className="w-4 h-4" /> Editar
                            </a>
                        )}
                        {acciones.map((a) =>
                            a.accion === 'liquidar' && esViaticos
                                ? (
                                    <a key={a.accion} href={route('viaticos.liquidacion', solicitud.id)}
                                        className={`flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg ${colorBotonAccion(a.accion)} text-white transition-colors`}>
                                        <IconoAccion accion={a.accion} /> {a.label ?? 'Presentar informe'}
                                    </a>
                                ) : (
                                    <a key={a.accion} onClick={() => setAccionActiva(a)}
                                        className={`flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg ${colorBotonAccion(a.accion)} text-white transition-colors capitalize cursor-pointer`}>
                                        <IconoAccion accion={a.accion} /> {a.label ?? a.accion}
                                    </a>
                                )
                        )}
                    </div>
                </div>

                {/* Aviso de rechazo o devolución */}
                {mostrarAviso && <AvisoRechazo transicion={ultimaTransicion} rutaEditar={rutaEditar} />}

                {/* Detalle del proceso */}
                <SeccionCard titulo="Detalle">
                    {esOficina && <DetalleOficina solicitable={solicitud.solicitable} />}
                    {esViaticos && <DetalleViaticos solicitable={solicitud.solicitable} />}
                </SeccionCard>

                {/* Línea de tiempo */}
                <SeccionCard titulo="Historial de movimientos">
                    <LineaTiempo transiciones={transiciones} />
                </SeccionCard>
            </div>

            <ModalAccion
                solicitudId={solicitud.id}
                accion={accionActiva}
                icono={<IconoAccion accion={accionActiva?.accion} />}
                onClose={() => setAccionActiva(null)}
            />
        </AppLayout>
    );
}
