import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

/**
 * Renueva la cookie XSRF-TOKEN pidiendo una ruta GET de la app (StartSession
 * la reemite en la respuesta). Sirve para recuperar la sesion tras un 419.
 */
function refrescarSesion() {
    return axios.get(route('notificaciones.index'));
}

/**
 * POST tolerante a "Pagina expirada" (419 CSRF): si la sesion caduco, refresca
 * el token y reintenta una sola vez, en vez de dejar caer la pagina de error.
 */
function postConReintento(url) {
    return axios.post(url).catch((error) => {
        if (error?.response?.status === 419) {
            return refrescarSesion().then(() => axios.post(url));
        }
        throw error;
    });
}

const IconBell = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M5.25 9a6.75 6.75 0 0 1 13.5 0v.75c0 2.123.8 4.057 2.118 5.52a.75.75 0 0 1-.297 1.206c-1.544.57-3.16.99-4.831 1.243a3.75 3.75 0 1 1-7.48 0 24.585 24.585 0 0 1-4.831-1.244.75.75 0 0 1-.298-1.205A8.217 8.217 0 0 0 5.25 9.75V9Zm4.502 8.9a2.25 2.25 0 1 0 4.496 0 25.057 25.057 0 0 1-4.496 0Z" clipRule="evenodd" />
    </svg>
);

const IconX = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z" clipRule="evenodd" />
    </svg>
);

const ESTILO_TIPO = {
    rechazada:        { dot: 'bg-red-500',    badge: 'bg-red-50 text-red-700 border-red-200' },
    devuelta:         { dot: 'bg-orange-500', badge: 'bg-orange-50 text-orange-700 border-orange-200' },
    accion_requerida: { dot: 'bg-amber-500',  badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    comision_reportada: { dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    ajustada:         { dot: 'bg-amber-500',   badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    reactivada:       { dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    cancelada:        { dot: 'bg-red-500',     badge: 'bg-red-50 text-red-700 border-red-200' },
    informativo:      { dot: 'bg-blue-500',   badge: 'bg-blue-50 text-blue-700 border-blue-200' },
};

function mensajeNotificacion(n) {
    switch (n.tipo) {
        case 'rechazada':
            return `${n.actor_nombre} rechazó tu solicitud ${n.radicado}`;
        case 'devuelta':
            return `${n.actor_nombre} devolvió tu solicitud ${n.radicado} para corrección`;
        case 'accion_requerida':
            return `Tienes una acción pendiente en ${n.radicado}`;
        case 'comision_reportada':
            return `Comisión pendiente por revisar: ${n.radicado}`;
        case 'ajustada':   return `Comisión ajustada: ${n.radicado}`;
        case 'cancelada':  return `Comisión cancelada: ${n.radicado}`;
        case 'reactivada': return `Comisión reactivada: ${n.radicado}`;
        default:
            return `Actualización en ${n.radicado}`;
    }
}

function ModalNotificacion({ notificacion, onClose }) {
    if (!notificacion) return null;
    const estilo = ESTILO_TIPO[notificacion.tipo] ?? ESTILO_TIPO.informativo;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
            <div
                className="bg-white rounded-xl shadow-xl w-full max-w-md p-6"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between mb-4">
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${estilo.badge}`}>
                        {notificacion.tipo_nombre}
                    </span>
                    <button onClick={onClose} className="text-slate-400 hover:text-slate-600">
                        <IconX className="w-5 h-5" />
                    </button>
                </div>

                <h3 className="text-base font-semibold text-slate-900 mb-1">
                    {mensajeNotificacion(notificacion)}
                </h3>
                <p className="text-xs font-mono text-slate-400 mb-4">{notificacion.radicado}</p>

                <dl className="space-y-3 text-sm">
                    {notificacion.solicitante && (
                        <div>
                            <dt className="text-xs text-slate-500 mb-0.5">Solicitante</dt>
                            <dd className="font-medium text-slate-800">{notificacion.solicitante}</dd>
                        </div>
                    )}

                    {(notificacion.tipo === 'rechazada' || notificacion.tipo === 'devuelta') && (
                        <>
                            {notificacion.actor_nombre && (
                                <div>
                                    <dt className="text-xs text-slate-500 mb-0.5">
                                        {notificacion.tipo === 'rechazada' ? 'Rechazado por' : 'Devuelto por'}
                                    </dt>
                                    <dd className="font-medium text-slate-800">{notificacion.actor_nombre}</dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-xs text-slate-500 mb-0.5">
                                    Razón de {notificacion.tipo === 'rechazada' ? 'rechazo' : 'devolución'}
                                </dt>
                                <dd className="text-slate-700 bg-slate-50 rounded-lg px-3 py-2 border border-slate-100">
                                    {notificacion.comentario || 'No se indicó una razón.'}
                                </dd>
                            </div>
                        </>
                    )}

                    <div>
                        <dt className="text-xs text-slate-500 mb-0.5">Fecha</dt>
                        <dd className="text-slate-700">{notificacion.creada_en}</dd>
                    </div>
                </dl>

                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={onClose}
                        className="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                        Cerrar
                    </button>
                    <button
                        onClick={() => router.visit(route('solicitudes.show', notificacion.solicitud_id))}
                        className="px-4 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                        Ver solicitud
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function PanelNotificaciones({ noLeidasIniciales = 0 }) {
    const [abierto, setAbierto] = useState(false);
    const [cargando, setCargando] = useState(false);
    const [notificaciones, setNotificaciones] = useState([]);
    const [noLeidas, setNoLeidas] = useState(noLeidasIniciales);
    const [seleccionada, setSeleccionada] = useState(null);
    const ref = useRef(null);

    useEffect(() => setNoLeidas(noLeidasIniciales), [noLeidasIniciales]);

    useEffect(() => {
        function handleClickFuera(e) {
            if (ref.current && !ref.current.contains(e.target)) setAbierto(false);
        }
        document.addEventListener('mousedown', handleClickFuera);
        return () => document.removeEventListener('mousedown', handleClickFuera);
    }, []);

    // Sondeo automatico: refresca el contador (y la lista si el panel esta abierto)
    // cada 45s, para que las notificaciones nuevas aparezcan sin recargar la pagina.
    // Ademas, cada GET reemite la cookie XSRF-TOKEN, manteniendo viva la sesion.
    useEffect(() => {
        const sondear = () => {
            if (document.hidden) return; // no sondear en pestañas en segundo plano
            axios.get(route('notificaciones.index'))
                .then(({ data }) => {
                    setNoLeidas(data.no_leidas);
                    setAbierto((estaAbierto) => {
                        if (estaAbierto) setNotificaciones(data.notificaciones);
                        return estaAbierto;
                    });
                })
                .catch(() => { /* silencioso: reintenta en el siguiente ciclo */ });
        };

        const id = setInterval(sondear, 45000);
        // Al volver el foco a la pestaña, sondear de inmediato: refresca la lista
        // y renueva la sesion antes de que el usuario haga clic (evita el 419).
        const onVisible = () => { if (!document.hidden) sondear(); };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            clearInterval(id);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, []);

    const cargarNotificaciones = () => {
        setCargando(true);
        axios.get(route('notificaciones.index'))
            .then(({ data }) => {
                setNotificaciones(data.notificaciones);
                setNoLeidas(data.no_leidas);
            })
            .finally(() => setCargando(false));
    };

    const toggle = () => {
        const next = !abierto;
        setAbierto(next);
        if (next) cargarNotificaciones();
    };

    const abrirDetalle = (n) => {
        setSeleccionada(n);
        setAbierto(false);
        if (!n.leida) {
            postConReintento(route('notificaciones.leer', n.id)).then(() => {
                setNotificaciones((prev) => prev.map((x) => x.id === n.id ? { ...x, leida: true } : x));
                setNoLeidas((c) => Math.max(0, c - 1));
            }).catch(() => { /* si aun falla, el detalle ya se abrio; se reintenta al proximo clic */ });
        }
    };

    const marcarTodasLeidas = () => {
        postConReintento(route('notificaciones.leer-todas')).then(() => {
            setNotificaciones((prev) => prev.map((x) => ({ ...x, leida: true })));
            setNoLeidas(0);
        }).catch(() => { /* silencioso: la sesion se recupera en el proximo ciclo */ });
    };

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                title="Notificaciones"
                onClick={toggle}
                className="relative w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors duration-150"
            >
                <IconBell className="w-5 h-5" />
                {noLeidas > 0 && (
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
                )}
            </button>

            {abierto && (
                <div className="absolute right-0 mt-2 w-96 bg-white rounded-xl border border-slate-200 shadow-lg z-40 overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                        <h3 className="text-sm font-semibold text-slate-800">Notificaciones</h3>
                        {noLeidas > 0 && (
                            <button onClick={marcarTodasLeidas} className="text-xs text-indigo-600 hover:underline">
                                Marcar todas como leídas
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {cargando ? (
                            <p className="text-sm text-slate-400 text-center py-8">Cargando…</p>
                        ) : notificaciones.length === 0 ? (
                            <p className="text-sm text-slate-400 text-center py-8">No tienes notificaciones.</p>
                        ) : (
                            notificaciones.map((n) => {
                                const estilo = ESTILO_TIPO[n.tipo] ?? ESTILO_TIPO.informativo;
                                return (
                                    <button
                                        key={n.id}
                                        onClick={() => abrirDetalle(n)}
                                        className={`w-full text-left px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition-colors flex gap-3 ${!n.leida ? 'bg-indigo-50/40' : ''}`}
                                    >
                                        <span className={`mt-1.5 w-2 h-2 rounded-full shrink-0 ${n.leida ? 'bg-transparent' : estilo.dot}`} />
                                        <div className="min-w-0">
                                            <p className={`text-sm ${n.leida ? 'text-slate-600' : 'text-slate-900 font-medium'}`}>
                                                {mensajeNotificacion(n)}
                                            </p>
                                            <p className="text-xs text-slate-400 mt-0.5">{n.creada_en}</p>
                                        </div>
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}

            <ModalNotificacion notificacion={seleccionada} onClose={() => setSeleccionada(null)} />
        </div>
    );
}
