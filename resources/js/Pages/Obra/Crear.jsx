import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { XCircleIcon, CheckCircleIcon, PlusCircleIcon, TrashIcon, ArrowRightIcon } from '@heroicons/react/24/outline';

function TextField({ label, value, onChange, error, type = 'text', multiline, ...props }) {
    const cls = [
        'w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
        error ? 'border-red-400' : 'border-slate-300',
    ].join(' ');

    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            {multiline
                ? <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={3} className={cls} {...props} />
                : <input type={type} value={value} onChange={(e) => onChange(e.target.value)} className={cls} {...props} />
            }
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}

const ITEM_VACIO = { especificacion: '', unidad: '', cantidad: 1, sede: '' };

export default function Crear({ nombreSugerido = '' }) {
    const titulo = 'Nueva solicitud de obra';

    const { data, setData, post, transform, processing, errors } = useForm({
        nombre_solicitante: nombreSugerido,
        fecha_solicitud:    '',
        fecha_entrega:      '',
        observacion:        '',
        items:              [{ ...ITEM_VACIO }],
        cotizacion:         null,
    });

    const agregarItem = () => setData('items', [...data.items, { ...ITEM_VACIO }]);
    const eliminarItem = (idx) => setData('items', data.items.filter((_, i) => i !== idx));
    const actualizarItem = (idx, campo, valor) => {
        const items = data.items.map((item, i) => i === idx ? { ...item, [campo]: valor } : item);
        setData('items', items);
    };

    // enviar=true crea la solicitud y la manda de una vez a RR. HH.; false la deja en borrador.
    const submit = (e, enviar = false) => {
        e.preventDefault();
        transform((datos) => ({
            ...datos,
            // Se descartan los items en blanco: asi, si el usuario solo adjunta la
            // cotizacion (sin llenar elementos), se envia items vacio y la validacion
            // (al menos un elemento O una cotizacion) pasa correctamente.
            items: (datos.items || []).filter((it) => (it.especificacion || '').trim() !== ''),
            enviar,
        }));
        post(route('obra.store'), { forceFormData: true });
    };

    return (
        <AppLayout title={titulo}>
            <Head title={titulo} />
            <div className="p-6 w-full">
                <h2 className="text-lg font-semibold text-slate-800 mb-6">{titulo}</h2>
                <form onSubmit={submit} className="space-y-6">
                    {/* Cabecera */}
                    <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Información general</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <TextField label="Solicitante:" value={data.nombre_solicitante}
                                onChange={(v) => setData('nombre_solicitante', v)}
                                error={errors.nombre_solicitante} />
                            <TextField label="Fecha de solicitud:" type="date" value={data.fecha_solicitud}
                                onChange={(v) => setData('fecha_solicitud', v)}
                                error={errors.fecha_solicitud} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <TextField label="Fecha de entrega (opcional):" type="date" value={data.fecha_entrega}
                                onChange={(v) => setData('fecha_entrega', v)}
                                error={errors.fecha_entrega} />
                        </div>
                        <TextField label="Observación (opcional):" value={data.observacion}
                            onChange={(v) => setData('observacion', v)} multiline
                            error={errors.observacion} />
                    </div>

                    {/* Ítems */}
                    <div className="bg-white rounded-xl border border-slate-200 p-5">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">Elementos</h3>
                            <button type="button" onClick={agregarItem}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 font-medium">
                                <PlusCircleIcon className="w-4 h-4" /> Agregar elemento
                            </button>
                        </div>
                        {errors.items && <p className="text-red-500 text-xs mb-3">{errors.items}</p>}
                        <div className="space-y-4">
                            {data.items.map((item, idx) => (
                                <div key={idx} className="border border-slate-100 rounded-lg p-4 space-y-3 relative">
                                    {data.items.length > 1 && (
                                        <button type="button" onClick={() => eliminarItem(idx)}
                                            className="absolute top-3 right-3 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-500 bg-white border border-red-300 rounded-lg hover:bg-red-50 transition-colors">
                                            <TrashIcon className="w-4 h-4 text-red-500" /> Eliminar
                                        </button>
                                    )}
                                    <div className="grid grid-cols-2 gap-3">
                                        <TextField label="Especificación" value={item.especificacion}
                                            onChange={(v) => actualizarItem(idx, 'especificacion', v)}
                                            error={errors[`items.${idx}.especificacion`]} />
                                        <TextField label="Unidad" value={item.unidad}
                                            onChange={(v) => actualizarItem(idx, 'unidad', v)}
                                            error={errors[`items.${idx}.unidad`]} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Cantidad</label>
                                            <input type="number" min={0} step="any" value={item.cantidad}
                                                onChange={(e) => actualizarItem(idx, 'cantidad', e.target.value)}
                                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none" />
                                            {errors[`items.${idx}.cantidad`] && <p className="text-red-500 text-xs mt-1">{errors[`items.${idx}.cantidad`]}</p>}
                                        </div>
                                        <TextField label="Sede" value={item.sede}
                                            onChange={(v) => actualizarItem(idx, 'sede', v)}
                                            error={errors[`items.${idx}.sede`]} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Cotización */}
                    <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">Cotización (opcional)</h3>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls"
                            onChange={(e) => setData('cotizacion', e.target.files[0] ?? null)}
                            className="block w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                        {errors.cotizacion && <p className="text-red-500 text-xs mt-1">{errors.cotizacion}</p>}
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3">
                        <a href={route('solicitudes.index')}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                          <XCircleIcon className="w-4 h-4" /> Cancelar
                        </a>
                        <button type="submit" disabled={processing}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                           <CheckCircleIcon className="w-4 h-4" />
                           {processing ? 'Guardando…' : 'Guardar borrador'}
                        </button>
                        <button type="button" disabled={processing} onClick={(e) => submit(e, true)}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                           <ArrowRightIcon className="w-4 h-4" />
                           {processing ? 'Enviando…' : 'Crear y enviar a RR. HH.'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
