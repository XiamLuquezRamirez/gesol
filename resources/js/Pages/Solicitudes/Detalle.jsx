import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import LineaTiempo from '@/Components/LineaTiempo';
import ModalAccion from '@/Components/ModalAccion';
import Modal from '@/Components/Modal';
import { formatearMoneda, formatearFecha } from '@/lib/format';
import { useState } from 'react';
import { usePage, router, useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { ArrowLeftIcon, ArrowRightIcon, ArrowUturnLeftIcon, CheckCircleIcon, CheckIcon, XCircleIcon, CreditCardIcon, PencilSquareIcon, PrinterIcon, EnvelopeIcon, EyeIcon } from '@heroicons/react/24/outline';

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
    enviar_revision: 'bg-blue-600 hover:bg-blue-700',
    reenviar:  'bg-yellow-600 hover:bg-yellow-700',
    cerrar:    'bg-slate-600 hover:bg-slate-700',
};

function colorBotonAccion(accion) {
    return COLORES_ACCION[accion] ?? 'bg-indigo-600 hover:bg-indigo-700';
}

function IconoAccion({ accion }) {
    const cls = 'w-4 h-4';
    switch (accion) {
        case 'enviar':
        case 'enviar_revision':
        case 'reenviar':
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
                                    <td className="px-3 py-2.5 text-right">{item.costo_estimado == null ? '—' : formatearMoneda(item.costo_estimado)}</td>
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

const etiquetaRubro = (r) =>
    r ? r.charAt(0).toUpperCase() + r.slice(1).replace(/[_-]/g, ' ') : '—';

const ETIQUETAS_PAGO = { efectivo: 'Efectivo', transferencia: 'Transferencia' };

function DetalleViaticos({ solicitable, solicitudId, cerrada }) {
    if (!solicitable) return null;
    const viajeros = solicitable.viajeros ?? [];
    const [rubrosDe, setRubrosDe] = useState(null); // viajero seleccionado para ver sus rubros

    const enviarCorreo = (viajeroId) => {
        router.post(route('liquidacion.correo', [solicitudId, viajeroId]), {}, { preserveScroll: true });
    };

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
                                        <th className="px-3 py-2 font-medium text-left whitespace-nowrap">Acciones</th>
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
                                           
                                          
                                                <td className="px-3 py-2.5 whitespace-nowrap">
                                                    <div className="flex items-center justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => setRubrosDe(v)}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-blue-600 border border-blue-300 hover:bg-blue-50 transition-colors hover:text-blue-700"
                                                        title="Ver rubros"
                                                    >
                                                        <EyeIcon className="w-4 h-4" /> Ver rubros
                                                    </button>
                                                    {cerrada && (
                                                        <>
                                                        <a
                                                            href={route('liquidacion.pdf', [solicitudId, v.id])}
                                                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-600 border border-slate-300 hover:bg-slate-50 transition-colors hover:text-slate-700"
                                                            title="Imprimir / descargar PDF"
                                                        >
                                                            <PrinterIcon className="w-4 h-4" /> Imprimir
                                                        </a>
                                                        <button
                                                            type="button"
                                                            onClick={() => enviarCorreo(v.id)}
                                                            disabled={!v.empleado?.email}
                                                            title={v.empleado?.email
                                                                ? `Enviar a ${v.empleado.email}`
                                                                : 'El empleado no tiene correo registrado'}
                                                            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-blue-600 font-medium rounded-lg border border-blue-300 hover:bg-blue-50 transition-colors hover:text-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                                        >
                                                            <EnvelopeIcon className="w-4 h-4" /> Correo
                                                        </button>
                                                        </>
                                                        )}  
                                                    </div>
                                                </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </>
            )}

            {/* Modal: rubros asignados al viajero */}
            <Modal show={rubrosDe !== null} onClose={() => setRubrosDe(null)} maxWidth="lg">
                {rubrosDe && (() => {
                    const asigs = rubrosDe.asignaciones ?? [];
                    const total = asigs.reduce((s, a) => s + Number(a.subtotal ?? 0), 0);
                    return (
                        <div className="p-6">
                            <div className="flex items-start justify-between mb-1">
                                <h3 className="text-base font-semibold text-slate-800">Rubros asignados</h3>
                                <button type="button" onClick={() => setRubrosDe(null)}
                                    className="text-slate-400 hover:text-slate-600 text-xl leading-none" aria-label="Cerrar">×</button>
                            </div>
                            <p className="text-sm text-slate-500 mb-4">
                                {rubrosDe.empleado ? `${rubrosDe.empleado.nombres} ${rubrosDe.empleado.apellidos}` : '—'}
                                {rubrosDe.tipo_pago ? ` · ${ETIQUETAS_PAGO[rubrosDe.tipo_pago] ?? rubrosDe.tipo_pago}` : ''}
                            </p>

                            {asigs.length === 0 ? (
                                <p className="text-sm text-slate-400 text-center py-6">Este viajero no tiene rubros asignados.</p>
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
                                            {asigs.map((a, i) => (
                                                <tr key={i} className="text-slate-700">
                                                    <td className="px-3 py-2.5">{etiquetaRubro(a.rubro)}</td>
                                                    <td className="px-3 py-2.5 text-right">{formatearMoneda(a.valor_unitario)}</td>
                                                    <td className="px-3 py-2.5 text-center">{a.dias}</td>
                                                    <td className="px-3 py-2.5 text-right font-medium">{formatearMoneda(a.subtotal)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot className="bg-slate-50 border-t border-slate-100">
                                            <tr>
                                                <td colSpan={3} className="px-3 py-2.5 text-right text-xs font-semibold text-slate-500">Total</td>
                                                <td className="px-3 py-2.5 text-right font-semibold text-slate-800">{formatearMoneda(total)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            )}

                            <div className="flex justify-end mt-5">
                                <button type="button" onClick={() => setRubrosDe(null)}
                                    className="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    );
                })()}
            </Modal>
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

function SeccionCotizacion({ solicitud, cotizacion }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        cotizaciones: [],
        comentario_contador: cotizacion.comentario ?? '',
    });

    const archivos = cotizacion.archivos ?? [];

    const submit = (e) => {
        e.preventDefault();
        post(route('oficina.cotizacion.anexar', solicitud.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reset('cotizaciones'),
        });
    };

    const eliminar = (cotizacionId) => {
        router.delete(route('oficina.cotizacion.eliminar', [solicitud.id, cotizacionId]), { preserveScroll: true });
    };

    const tieneAlgo = archivos.length > 0 || cotizacion.comentario;

    return (
        <SeccionCard titulo="Cotización y comentario para el contador">
            {/* Vista para todos: lo ya anexado */}
            {tieneAlgo ? (
                <div className="space-y-3 mb-4">
                    {cotizacion.comentario && (
                        <div>
                            <p className="text-xs text-slate-500 mb-0.5">Comentario</p>
                            <p className="text-sm text-slate-800 whitespace-pre-line">{cotizacion.comentario}</p>
                        </div>
                    )}
                    {archivos.length > 0 && (
                        <div>
                            <p className="text-xs text-slate-500 mb-1">Archivos ({archivos.length})</p>
                            <ul className="space-y-1.5">
                                {archivos.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2">
                                        <div className="min-w-0">
                                            <a
                                                href={route('oficina.cotizacion.descargar', [solicitud.id, a.id])}
                                                className="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:underline min-w-0"
                                            >
                                                <ArrowRightIcon className="w-4 h-4 shrink-0" />
                                                <span className="truncate">{a.nombre}</span>
                                            </a>
                                            {a.autor && <p className="text-xs text-slate-400 pl-6">Subido por {a.autor}</p>}
                                        </div>
                                        {a.puede_gestionar && (
                                            <div className="flex items-center gap-2 shrink-0">
                                                <label className="text-xs text-indigo-600 hover:underline cursor-pointer">
                                                    Actualizar
                                                    <input
                                                        type="file"
                                                        className="hidden"
                                                        accept=".pdf,.jpg,.jpeg,.png"
                                                        onChange={(e) => {
                                                            const archivo = e.target.files[0];
                                                            if (!archivo) return;
                                                            router.post(
                                                                route('oficina.cotizacion.actualizar', [solicitud.id, a.id]),
                                                                { cotizacion: archivo },
                                                                { forceFormData: true, preserveScroll: true }
                                                            );
                                                            // Limpiar el input para poder re-subir el mismo archivo si hace falta.
                                                            e.target.value = '';
                                                        }}
                                                    />
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={() => eliminar(a.id)}
                                                    className="p-1 rounded text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors shrink-0"
                                                    title="Eliminar archivo"
                                                >
                                                    <XCircleIcon className="w-4 h-4" />
                                                </button>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            ) : (
                !cotizacion.puede_anexar && (
                    <p className="text-sm text-slate-400">Aún no se ha anexado cotización ni comentario.</p>
                )
            )}

            {/* Formulario solo para RR. HH. / solicitante mientras el estado lo permita */}
            {cotizacion.puede_anexar && (
                <form onSubmit={submit} className="space-y-3 border-t border-slate-100 pt-4">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">
                            Agregar cotización(es) (PDF o imagen — puede seleccionar varios)
                        </label>
                        <input
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) => setData('cotizaciones', Array.from(e.target.files))}
                            className="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        {(errors.cotizaciones || errors['cotizaciones.0']) && (
                            <p className="text-red-500 text-xs mt-1">{errors.cotizaciones ?? errors['cotizaciones.0']}</p>
                        )}
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-600 mb-1">Comentario para el contador</label>
                        <textarea
                            rows={3}
                            value={data.comentario_contador}
                            onChange={(e) => setData('comentario_contador', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
                            placeholder="Notas o aclaraciones sobre la cotización…"
                        />
                        {errors.comentario_contador && <p className="text-red-500 text-xs mt-1">{errors.comentario_contador}</p>}
                    </div>
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50"
                        >
                            <CheckCircleIcon className="w-4 h-4" /> {processing ? 'Guardando…' : 'Guardar'}
                        </button>
                    </div>
                </form>
            )}
        </SeccionCard>
    );
}

export default function Detalle({ solicitud, acciones, rutaEditar, rutaLiquidacion }) {
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
                        {rutaLiquidacion && (
                            <a href={rutaLiquidacion} className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-teal-600 text-white hover:bg-teal-700 transition-colors">
                                <PencilSquareIcon className="w-4 h-4" /> Editar liquidación
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
                    {esViaticos && (
                        <DetalleViaticos
                            solicitable={solicitud.solicitable}
                            solicitudId={solicitud.id}
                            cerrada={solicitud.estado === 'cerrada'}
                        />
                    )}
                </SeccionCard>

                {/* Cotización y comentario para el contador (solo oficina) */}
                {esOficina && solicitud.cotizacion && (
                    <SeccionCotizacion solicitud={solicitud} cotizacion={solicitud.cotizacion} />
                )}

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
