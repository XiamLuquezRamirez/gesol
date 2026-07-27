import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const ACCIONES_CON_RAZON = ['rechazar', 'devolver'];

function labelComentario(accion) {
    if (accion === 'rechazar') return 'Razón del rechazo';
    if (accion === 'devolver') return 'Razón de la devolución';
    return 'Comentario';
}

export default function ModalAccion({ solicitudId, accion, onClose, icono }) {
    const esPago       = accion?.accion === 'pagar';
    const requiereRazon = ACCIONES_CON_RAZON.includes(accion?.accion);

    const { data, setData, post, processing, errors, reset } = useForm({
        accion:                    accion?.accion ?? '',
        comentario:                '',
        'metadatos[valor_pagado]': '',
        'metadatos[fecha_pago]':   '',
        'metadatos[comprobante]':  '',
    });

    useEffect(() => {
        setData('accion', accion?.accion ?? '');
    }, [accion]);

    const submit = (e) => {
        e.preventDefault();
        post(route('solicitudes.transicion', { solicitud: solicitudId }), {
            onSuccess: () => { reset(); onClose(); },
        });
    };
    

    if (!accion) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 className="text-base font-semibold text-slate-900 mb-4 capitalize">
                    Confirmar: {accion.accion}
                </h3>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">
                            {labelComentario(accion?.accion)}
                            {requiereRazon
                                ? <span className="text-red-500 ml-1">*</span>
                                : <span className="text-slate-400 text-xs font-normal ml-1">(opcional)</span>
                            }
                        </label>
                        <textarea
                            className="w-full rounded-lg border-slate-300 text-sm"
                            rows={3}
                            required={requiereRazon}
                            placeholder={requiereRazon ? 'Explique brevemente el motivo...' : ''}
                            value={data.comentario}
                            onChange={e => setData('comentario', e.target.value)}
                        />
                        {errors.comentario && <p className="text-red-500 text-xs mt-1">{errors.comentario}</p>}
                    </div>

                    {esPago && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Valor pagado (COP)</label>
                                <input type="number" className="w-full rounded-lg border-slate-300 text-sm"
                                    value={data['metadatos[valor_pagado]']}
                                    onChange={e => setData('metadatos[valor_pagado]', e.target.value)} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Fecha de pago</label>
                                <input type="date" className="w-full rounded-lg border-slate-300 text-sm"
                                    value={data['metadatos[fecha_pago]']}
                                    onChange={e => setData('metadatos[fecha_pago]', e.target.value)} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Comprobante</label>
                                <input type="text" className="w-full rounded-lg border-slate-300 text-sm"
                                    value={data['metadatos[comprobante]']}
                                    onChange={e => setData('metadatos[comprobante]', e.target.value)} />
                            </div>
                        </>
                    )}

                    {errors.accion && <p className="text-red-500 text-sm">{errors.accion}</p>}

                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-2 px-4 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors disabled:opacity-50 capitalize">
                            {icono} {accion.accion}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
