import AppLayout from '@/Layouts/AppLayout';
import { useForm } from '@inertiajs/react';

function TextField({ label, value, onChange, error, type = 'text', ...props }) {
    return (
        <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={[
                    'w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
                    error ? 'border-red-400' : 'border-slate-300',
                ].join(' ')}
                {...props}
            />
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}

export default function Crear({ usuarios }) {
    const { data, setData, post, processing, errors } = useForm({
        nombre_comision:   '',
        municipio_destino: '',
        motivo:            '',
        fecha_salida:      '',
        fecha_regreso:     '',
        viajeros:          [],
    });

    const toggleViajero = (id) => {
        const idNum = Number(id);
        setData('viajeros',
            data.viajeros.includes(idNum)
                ? data.viajeros.filter((v) => v !== idNum)
                : [...data.viajeros, idNum]
        );
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('viaticos.store'));
    };

    return (
        <AppLayout title="Nueva solicitud de viáticos">
            <div className="p-6 max-w-2xl mx-auto w-full">
                <h2 className="text-lg font-semibold text-slate-800 mb-6">Nueva solicitud de viáticos</h2>
                <form onSubmit={submit} className="space-y-6">
                    <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Datos de la comisión</h3>
                        <TextField label="Nombre de la comisión" value={data.nombre_comision}
                            onChange={(v) => setData('nombre_comision', v)} error={errors.nombre_comision} />
                        <TextField label="Municipio destino" value={data.municipio_destino}
                            onChange={(v) => setData('municipio_destino', v)} error={errors.municipio_destino} />
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Motivo</label>
                            <textarea value={data.motivo} rows={3}
                                onChange={(e) => setData('motivo', e.target.value)}
                                className={`w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none ${errors.motivo ? 'border-red-400' : 'border-slate-300'}`} />
                            {errors.motivo && <p className="text-red-500 text-xs mt-1">{errors.motivo}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <TextField label="Fecha de salida" type="date" value={data.fecha_salida}
                                onChange={(v) => setData('fecha_salida', v)} error={errors.fecha_salida} />
                            <TextField label="Fecha de regreso" type="date" value={data.fecha_regreso}
                                onChange={(v) => setData('fecha_regreso', v)} error={errors.fecha_regreso} />
                        </div>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 p-5">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">Viajeros</h3>
                        {errors.viajeros && <p className="text-red-500 text-xs mb-3">{errors.viajeros}</p>}
                        <div className="space-y-2 max-h-56 overflow-y-auto">
                            {usuarios.map((u) => (
                                <label key={u.id} className="flex items-center gap-3 cursor-pointer rounded-lg px-3 py-2 hover:bg-slate-50">
                                    <input
                                        type="checkbox"
                                        checked={data.viajeros.includes(u.id)}
                                        onChange={() => toggleViajero(u.id)}
                                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span className="text-sm text-slate-700">{u.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end gap-3">
                        <a href={route('solicitudes.index')}
                            className="px-5 py-2 text-sm text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" disabled={processing}
                            className="px-5 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            Crear solicitud
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
