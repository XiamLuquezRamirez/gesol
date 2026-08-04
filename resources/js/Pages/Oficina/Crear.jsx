import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import { Head, useForm } from '@inertiajs/react';
import { XCircleIcon, CheckCircleIcon, PlusCircleIcon, TrashIcon } from '@heroicons/react/24/outline';

function SelectField({ label, name, value, onChange, options, error, placeholder }) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={[
                    'w-full rounded-lg border text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none',
                    error ? 'border-red-400' : 'border-slate-300',
                ].join(' ')}
            >
                {placeholder && <option value="">{placeholder}</option>}
                {options.map((o) => (
                    <option key={o.id} value={o.id}>{o.nombre ?? o.name}</option>
                ))}
            </select>
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}

function TextField({ label, name, value, onChange, error, multiline, ...props }) {
    const cls = [
        'w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
        error ? 'border-red-400' : 'border-slate-300',
    ].join(' ');

    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            {multiline
                ? <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={3} className={cls} {...props} />
                : <input type="text" value={value} onChange={(e) => onChange(e.target.value)} className={cls} {...props} />
            }
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}

const ITEM_VACIO = { nombre: '', categoria: 'producto', cantidad: 1, costo_estimado: '', notas: '' };

export default function Crear({ areas, usuarios, solicitud, editar }) {
    const titulo = editar ? 'Editar solicitud de oficina' : 'Nueva solicitud de oficina';
    const solicitable = solicitud?.solicitable;

    const { data, setData, post, put, processing, errors } = useForm({
        area_id:          solicitud?.area_id ?? '',
        beneficiario:  solicitable?.beneficiario ?? '',
        urgencia:         solicitable?.urgencia ?? 'media',
        justificacion:    solicitable?.justificacion ?? '',
        items:            solicitable?.items?.map(i => ({
            nombre:          i.nombre,
            categoria:       i.categoria,
            cantidad:        i.cantidad,
            costo_estimado:  i.costo_estimado,
            notas:           i.notas ?? '',
        })) ?? [{ ...ITEM_VACIO }],
    });

    //

    const agregarItem = () => setData('items', [...data.items, { ...ITEM_VACIO }]);
    const eliminarItem = (idx) => setData('items', data.items.filter((_, i) => i !== idx));
    const actualizarItem = (idx, campo, valor) => {
        const items = data.items.map((item, i) => i === idx ? { ...item, [campo]: valor } : item);
        setData('items', items);
    };

    const submit = (e) => {
        e.preventDefault();
        if (editar) {
            put(route('oficina.update', solicitud.id));
        } else {
            post(route('oficina.store'));
        }
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
                            <SelectField label="Departamento:" name="area_id" value={data.area_id}
                                onChange={(v) => setData('area_id', v)}
                                options={areas} error={errors.area_id} placeholder="Seleccionar departamento" />
                            <TextField label="Beneficiario(s):" value={data.beneficiario}
                                onChange={(v) => setData('beneficiario', v)}
                                error={errors.beneficiario} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Urgencia</label>
                                <select value={data.urgencia} onChange={(e) => setData('urgencia', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="baja">Baja</option>
                                    <option value="media">Media</option>
                                    <option value="alta">Alta</option>
                                </select>
                            </div>
                            
                        </div>
                        <TextField label="Justificación:" name="justificacion" value={data.justificacion}
                            onChange={(v) => setData('justificacion', v)} multiline
                            error={errors.justificacion} />
                    </div>

                    {/* Ítems */}
                    <div className="bg-white rounded-xl border border-slate-200 p-5">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">Ítems</h3>
                            <button type="button" onClick={agregarItem}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 font-medium">
                                <PlusCircleIcon className="w-4 h-4" /> Agregar ítem
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
                                        <TextField label="Nombre" value={item.nombre}
                                            onChange={(v) => actualizarItem(idx, 'nombre', v)}
                                            error={errors[`items.${idx}.nombre`]} />
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Categoría</label>
                                            <select value={item.categoria}
                                                onChange={(e) => actualizarItem(idx, 'categoria', e.target.value)}
                                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                                                <option value="producto">Producto</option>
                                                <option value="servicio">Servicio</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Cantidad</label>
                                            <input type="number" min={1} value={item.cantidad}
                                                onChange={(e) => actualizarItem(idx, 'cantidad', parseInt(e.target.value) || 1)}
                                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none" />
                                        </div>
                                        <CampoMoneda label="Costo estimado (opcional)" value={item.costo_estimado}
                                            onChange={(v) => actualizarItem(idx, 'costo_estimado', v)}
                                            error={errors[`items.${idx}.costo_estimado`]} />
                                    </div>
                                    <TextField label="Notas (opcional)" value={item.notas}
                                        onChange={(v) => actualizarItem(idx, 'notas', v)} />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3">
                        <a href={route('solicitudes.index')}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                          <XCircleIcon className="w-4 h-4" /> Cancelar
                        </a>
                        <button type="submit" disabled={processing}
                            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                           <CheckCircleIcon className="w-4 h-4" /> {editar ? 'Guardar cambios' : 'Crear solicitud'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
