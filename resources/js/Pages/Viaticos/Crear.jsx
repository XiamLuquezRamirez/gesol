import AppLayout from '@/Layouts/AppLayout';
import MultiSelectBuscador from '@/Components/MultiSelectBuscador';
import { useForm, Head } from '@inertiajs/react';
import { useState } from 'react';
import { XCircleIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/react/24/outline';

const VIAJERO_VACIO = {
    empleado_id:   '',
    motivo:       '',
    fecha_salida: '',
    hora_salida:  '',
    fecha_regreso:'',
    hora_regreso: '',
};

function Field({ label, error, children }) {
    return (
        <div>
            <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>
            {children}
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}

function Input({ error, ...props }) {
    return (
        <input
            className={[
                'w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
                error ? 'border-red-400' : 'border-slate-300',
            ].join(' ')}
            {...props}
        />
    );
}

function Textarea({ error, ...props }) {
    return (
        <textarea
            rows={2}
            className={[
                'w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none resize-none',
                error ? 'border-red-400' : 'border-slate-300',
            ].join(' ')}
            {...props}
        />
    );
}

function IconTrash() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-4 h-4">
            <path fillRule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clipRule="evenodd" />
        </svg>
    );
}

function IconPlus() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-4 h-4">
            <path fillRule="evenodd" d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75v6.75a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z" clipRule="evenodd" />
        </svg>
    );
}

export default function Crear({ empleados, solicitud = null, editar = false, municipios = [] }) {
    const solicitable = solicitud?.solicitable ?? null;

    const viajerosIniciales = (solicitable?.viajeros ?? []).map((v) => ({
        empleado_id:   v.empleado_id,
        motivo:        v.motivo ?? '',
        fecha_salida:  String(v.fecha_salida ?? '').substring(0, 10),
        hora_salida:   v.hora_salida ?? '',
        fecha_regreso: String(v.fecha_regreso ?? '').substring(0, 10),
        hora_regreso:  v.hora_regreso ?? '',
        nombre: v.empleado ? `${v.empleado.nombres} ${v.empleado.apellidos}` : '',
    }));

    const { data, setData, post, put, processing, errors } = useForm({
        nombre_comision:   solicitable?.nombre_comision   ?? '',
        municipios: solicitable?.municipios?.map((m) => m.id) ?? [],
        observacion:       solicitable?.observacion       ?? '',
        viajeros:          viajerosIniciales,
    });

    const [form, setForm]         = useState(VIAJERO_VACIO);
    const [formError, setFormError] = useState({});

    const setF = (campo, valor) => setForm((p) => ({ ...p, [campo]: valor }));

    const validarForm = () => {
        const e = {};
        if (!form.empleado_id)   e.empleado_id  = 'Seleccione el empleado.';
        if (!form.motivo.trim()) e.motivo        = 'El motivo es obligatorio.';
        if (!form.fecha_salida)  e.fecha_salida  = 'Ingrese la fecha de salida.';
        if (!form.hora_salida)   e.hora_salida   = 'Ingrese la hora de salida.';
        if (!form.fecha_regreso) e.fecha_regreso = 'Ingrese la fecha de regreso.';
        if (!form.hora_regreso)  e.hora_regreso  = 'Ingrese la hora de regreso.';
        setFormError(e);
        return Object.keys(e).length === 0;
    };

    const agregarViajero = () => {
        if (!validarForm()) return;
        const empleado = empleados.find((e) => e.id === Number(form.empleado_id));
        const nombre = empleado ? `${empleado.nombres} ${empleado.apellidos}` : '';
        setData('viajeros', [
            ...data.viajeros,
            { ...form, empleado_id: Number(form.empleado_id), nombre },
        ]);
        setForm(VIAJERO_VACIO);
        setFormError({});
    };

    const eliminarViajero = (idx) =>
        setData('viajeros', data.viajeros.filter((_, i) => i !== idx));

    const submit = (e) => {
        e.preventDefault();
        if (editar) put(route('viaticos.update', solicitud.id));
        else        post(route('viaticos.store'));
    };

    const formatFechaHora = (fecha, hora) =>
        fecha && hora ? `${fecha.split('-').reverse().join('/')} ${hora}` : '—';

    return (
        <AppLayout title={editar ? 'Editar solicitud de viáticos' : 'Nueva solicitud de viáticos'}>
            <Head title={editar ? 'Editar solicitud de viáticos' : 'Nueva solicitud de viáticos'} />
            <div className="p-6 w-full">
                <h2 className="text-lg font-semibold text-slate-800 mb-6">
                    {editar ? 'Editar solicitud de viáticos' : 'Nueva solicitud de viáticos'}
                </h2>

                <form onSubmit={submit} className="space-y-6">

                    {/* ── Información general ── */}
                    <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Información general
                        </h3>
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Nombre de la comisión" error={errors.nombre_comision}>
                                <Input
                                    value={data.nombre_comision}
                                    onChange={(e) => setData('nombre_comision', e.target.value)}
                                    error={errors.nombre_comision}
                                    placeholder="Ej. Visita técnica regional"
                                />
                            </Field>
                            <Field label="Municipios destino" error={errors.municipios}>
                                <MultiSelectBuscador
                                    opciones={municipios}
                                    seleccionados={data.municipios}
                                    onChange={(ids) => setData('municipios', ids)}
                                    placeholder="Buscar municipio…"
                                    vacio="No hay municipios registrados."
                                />
                            </Field>
                        </div>
                        <Field label="Observación general" error={errors.observacion}>
                            <Textarea
                                value={data.observacion}
                                onChange={(e) => setData('observacion', e.target.value)}
                                placeholder="Notas o instrucciones generales de la comisión (opcional)..."
                            />
                        </Field>
                    </div>

                    {/* ── Viajeros ── */}
                    <div className="bg-white rounded-xl border border-slate-200 p-5 space-y-5">
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Viajeros
                        </h3>
                        {errors.viajeros && (
                            <p className="text-red-500 text-xs">{errors.viajeros}</p>
                        )}

                        {/* Mini-formulario de viajero */}
                        <div className="border border-dashed border-slate-300 rounded-xl p-4 bg-slate-50 space-y-3">
                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                Agregar viajero
                            </p>

                            <Field label="Nombre del viajero" error={formError.empleado_id}>
                                <select
                                    value={form.empleado_id}
                                    onChange={(e) => setF('empleado_id', e.target.value)}
                                    className={[
                                        'w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
                                        formError.empleado_id ? 'border-red-400' : 'border-slate-300',
                                    ].join(' ')}
                                >
                                    <option value="">— Seleccionar viajero —</option>
                                    {empleados.map((e) => (
                                        <option key={e.id} value={e.id}>{e.nombres} {e.apellidos}</option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Motivo del viaje" error={formError.motivo}>
                                <Textarea
                                    value={form.motivo}
                                    onChange={(e) => setF('motivo', e.target.value)}
                                    placeholder="Describa el motivo del desplazamiento..."
                                    error={formError.motivo}
                                />
                            </Field>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-3">
                                    <Field label="Fecha de salida" error={formError.fecha_salida}>
                                        <Input
                                            type="date"
                                            value={form.fecha_salida}
                                            onChange={(e) => setF('fecha_salida', e.target.value)}
                                            error={formError.fecha_salida}
                                        />
                                    </Field>
                                    <Field label="Hora de salida" error={formError.hora_salida}>
                                        <Input
                                            type="time"
                                            value={form.hora_salida}
                                            onChange={(e) => setF('hora_salida', e.target.value)}
                                            error={formError.hora_salida}
                                        />
                                    </Field>
                                </div>
                                <div className="space-y-3">
                                    <Field label="Fecha de regreso" error={formError.fecha_regreso}>
                                        <Input
                                            type="date"
                                            value={form.fecha_regreso}
                                            onChange={(e) => setF('fecha_regreso', e.target.value)}
                                            error={formError.fecha_regreso}
                                        />
                                    </Field>
                                    <Field label="Hora de regreso" error={formError.hora_regreso}>
                                        <Input
                                            type="time"
                                            value={form.hora_regreso}
                                            onChange={(e) => setF('hora_regreso', e.target.value)}
                                            error={formError.hora_regreso}
                                        />
                                    </Field>
                                </div>
                            </div>

                            <div className="flex justify-end pt-1">
                                <button
                                    type="button"
                                    onClick={agregarViajero}
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors"
                                >
                                    <PlusCircleIcon className="w-4 h-4" /> Agregar viajero
                                </button>
                            </div>
                        </div>

                        {/* Tabla de viajeros agregados */}
                        {data.viajeros.length > 0 && (
                            <div className="overflow-x-auto rounded-xl border border-slate-200">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 border-b border-slate-200">
                                        <tr>
                                            <th className="text-left text-xs font-semibold text-slate-500 px-4 py-2.5">Viajero</th>
                                            <th className="text-left text-xs font-semibold text-slate-500 px-4 py-2.5">Motivo</th>
                                            <th className="text-left text-xs font-semibold text-slate-500 px-4 py-2.5 whitespace-nowrap">Salida</th>
                                            <th className="text-left text-xs font-semibold text-slate-500 px-4 py-2.5 whitespace-nowrap">Regreso</th>
                                            <th className="px-4 py-2.5"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {data.viajeros.map((v, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-4 py-3 font-medium text-slate-800 whitespace-nowrap">
                                                    {v.nombre}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 max-w-xs">
                                                    <p className="truncate" title={v.motivo}>{v.motivo}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 whitespace-nowrap">
                                                    {formatFechaHora(v.fecha_salida, v.hora_salida)}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600 whitespace-nowrap">
                                                    {formatFechaHora(v.fecha_regreso, v.hora_regreso)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <button
                                                        type="button"
                                                        onClick={() => eliminarViajero(idx)}
                                                        className="text-slate-400 hover:text-red-500 transition-colors"
                                                        title="Eliminar viajero"
                                                    >
                                                        <IconTrash />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {data.viajeros.length === 0 && (
                            <p className="text-xs text-slate-400 text-center py-2">
                                Aún no se han agregado viajeros.
                            </p>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3">
                        <a href={editar ? route('solicitudes.show', solicitud.id) : route('solicitudes.index')}
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
