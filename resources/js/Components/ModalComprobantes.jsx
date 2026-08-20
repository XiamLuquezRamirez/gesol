import Modal from '@/Components/Modal';
import { router } from '@inertiajs/react';
import { XMarkIcon, PaperClipIcon, ArrowDownTrayIcon, TrashIcon } from '@heroicons/react/24/outline';

/**
 * Modal para gestionar los comprobantes de transferencia de un viajero.
 * Una transferencia puede hacerse desde varias cuentas, así que admite varios
 * comprobantes. Ver (descargar), agregar (multi-archivo) y eliminar.
 *
 * Props:
 * - viajero: objeto del viajero (con id, nombre a mostrar y archivos[]).
 * - solicitudId: id de la solicitud.
 * - puedeGestionar: bool — habilita agregar/eliminar (según la policy del backend).
 * - onClose: () => void — cierra el modal.
 */
export default function ModalComprobantes({ viajero, solicitudId, puedeGestionar = false, onClose }) {
    const abierto = viajero !== null && viajero !== undefined;
    const comprobantes = abierto
        ? (viajero.archivos ?? []).filter((a) => a.tipo === 'comprobante')
        : [];

    const nombreViajero = abierto
        ? (viajero.empleado
            ? `${viajero.empleado.nombres} ${viajero.empleado.apellidos}`
            : (viajero.nombre_externo || 'Viajero'))
        : '';

    const subir = (fileList) => {
        if (!fileList || fileList.length === 0) return;
        router.post(
            route('viaticos.archivos.store', [solicitudId, viajero.id]),
            { tipo: 'comprobante', archivos: Array.from(fileList) },
            { forceFormData: true, preserveScroll: true }
        );
    };

    const eliminar = (archivoId) => {
        router.delete(route('viaticos.archivos.destroy', [solicitudId, viajero.id, archivoId]), { preserveScroll: true });
    };

    const formatoFecha = (iso) => {
        if (!iso) return '';
        return new Date(iso).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    return (
        <Modal show={abierto} onClose={onClose} maxWidth="lg">
            {abierto && (
                <div className="p-6">
                    <div className="flex items-start justify-between mb-4">
                        <div>
                            <h3 className="text-base font-semibold text-slate-800">Comprobantes de transferencia</h3>
                            <p className="text-xs text-slate-500 mt-0.5">{nombreViajero}</p>
                        </div>
                        <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
                            <XMarkIcon className="w-5 h-5" />
                        </button>
                    </div>

                    {comprobantes.length === 0 ? (
                        <p className="text-sm text-slate-400 py-4 text-center">
                            No hay comprobantes adjuntos.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100 border border-slate-100 rounded-lg mb-4">
                            {comprobantes.map((a) => (
                                <li key={a.id} className="flex items-center gap-3 px-3 py-2.5">
                                    <PaperClipIcon className="w-4 h-4 text-slate-400 shrink-0" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-slate-700 truncate">{a.nombre}</p>
                                        <p className="text-xs text-slate-400">
                                            {a.autor || 'Desconocido'}{a.created_at ? ` · ${formatoFecha(a.created_at)}` : ''}
                                        </p>
                                    </div>
                                    <a
                                        href={route('viaticos.archivos.descargar', [solicitudId, viajero.id, a.id])}
                                        className="p-1.5 rounded text-slate-500 hover:text-indigo-600 hover:bg-indigo-50"
                                        title="Descargar"
                                    >
                                        <ArrowDownTrayIcon className="w-4 h-4" />
                                    </a>
                                    {puedeGestionar && (
                                        <button
                                            type="button"
                                            onClick={() => eliminar(a.id)}
                                            className="p-1.5 rounded text-slate-400 hover:text-red-600 hover:bg-red-50"
                                            title="Eliminar"
                                        >
                                            <TrashIcon className="w-4 h-4" />
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    {puedeGestionar ? (
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">
                                Adjuntar comprobante(s) — PDF o imagen
                            </label>
                            <input
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => { subir(e.target.files); e.target.value = ''; }}
                                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                        </div>
                    ) : (
                        <p className="text-xs text-slate-400">Solo lectura.</p>
                    )}
                </div>
            )}
        </Modal>
    );
}
