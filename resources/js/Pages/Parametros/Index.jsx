import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircleIcon, PencilSquareIcon, PlusCircleIcon, TrashIcon, UserPlusIcon, XCircleIcon } from '@heroicons/react/24/outline';

const etiquetaRubro = (r) =>
    r.charAt(0).toUpperCase() + r.slice(1).replace(/[_-]/g, ' ');

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
                error ? 'border-red-400 bg-red-50' : 'border-slate-300',
            ].join(' ')}
            {...props}
        />
    );
}

/* ─── Tab Tarifas ─────────────────────────────────────── */
const TARIFA_VACIA = { rubro: '', valor_sugerido: 0 };

function TabTarifas({ tarifas }) {
    const [panel, setPanel]       = useState(null); // null | { tipo: 'crear'|'editar', id: null|number }
    const [confirmarId, setConfirmarId] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm(TARIFA_VACIA);

    const abrirCrear = () => {
        reset();
        clearErrors();
        setPanel({ tipo: 'crear', id: null });
    };

    const abrirEditar = (t) => {
        setData({ rubro: t.rubro, valor_sugerido: t.valor_sugerido });
        clearErrors();
        setPanel({ tipo: 'editar', id: t.id });
    };

    const cancelar = () => { setPanel(null); clearErrors(); };

    const submit = (e) => {
        e.preventDefault();
        if (panel.tipo === 'crear') {
            post(route('parametros.tarifas.store'), { onSuccess: () => setPanel(null) });
        } else {
            put(route('parametros.tarifas.update', panel.id), { onSuccess: () => setPanel(null) });
        }
    };

    const eliminar = (t) => {
        if (confirmarId !== t.id) { setConfirmarId(t.id); return; }
        router.delete(route('parametros.tarifas.destroy', t.id), { onSuccess: () => setConfirmarId(null) });
    };

    return (
        <div className="space-y-4">
            {/* Botón nuevo */}
            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={abrirCrear}
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors"
                >
                    <PlusCircleIcon className="w-4 h-4" /> Nueva tarifa
                </button>
            </div>

            {/* Panel crear / editar */}
            {panel && (
                <div className="bg-white rounded-xl border border-indigo-200 p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-indigo-500 mb-4">
                        {panel.tipo === 'crear' ? 'Nueva tarifa' : 'Editar tarifa'}
                    </p>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Nombre del rubro" error={errors.rubro}>
                                <Input
                                    value={data.rubro}
                                    onChange={(e) => setData('rubro', e.target.value)}
                                    error={errors.rubro}
                                    placeholder="Ej. hospedaje, peaje, taxi"
                                />
                            </Field>
                            <Field label="Valor sugerido / día" error={errors.valor_sugerido}>
                                <CampoMoneda
                                    value={data.valor_sugerido}
                                    onChange={(v) => setData('valor_sugerido', v)}
                                    error={errors.valor_sugerido}
                                />
                            </Field>
                        </div>
                        <div className="flex justify-end gap-3 pt-1">
                            <button type="button" onClick={cancelar}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                                <XCircleIcon className="w-4 h-4" /> Cancelar
                            </button>
                            <button type="submit" disabled={processing}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50">
                                <CheckCircleIcon className="w-4 h-4" />
                                {processing ? 'Guardando…' : panel.tipo === 'crear' ? 'Crear tarifa' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Tabla */}
            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                {tarifas.length === 0 ? (
                    <p className="text-sm text-slate-400 text-center py-10">No hay tarifas registradas.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th className="text-left text-xs font-semibold text-slate-500 px-5 py-3">Rubro</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-5 py-3">Valor sugerido / día</th>
                                <th className="px-5 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {tarifas.map((t) => (
                                <tr key={t.id} className="hover:bg-slate-50/50">
                                    <td className="px-5 py-3 font-medium text-slate-700">
                                        {etiquetaRubro(t.rubro)}
                                    </td>
                                    <td className="px-5 py-3 text-slate-600">
                                        {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(t.valor_sugerido)}
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <button type="button" onClick={() => abrirEditar(t)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Editar">
                                                <PencilSquareIcon className="w-4 h-4" />
                                            </button>
                                            {confirmarId === t.id ? (
                                                <div className="flex items-center gap-1 ml-1">
                                                    <button type="button" onClick={() => eliminar(t)}
                                                        className="px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">
                                                        Confirmar
                                                    </button>
                                                    <button type="button" onClick={() => setConfirmarId(null)}
                                                        className="px-2 py-1 text-xs font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg">
                                                        No
                                                    </button>
                                                </div>
                                            ) : (
                                                <button type="button" onClick={() => eliminar(t)}
                                                    className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Eliminar">
                                                    <TrashIcon className="w-4 h-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

/* ─── Tab Empleados ───────────────────────────────────── */
const VACIO = { area_id: '', identificacion: '', nombres: '', apellidos: '', email: '', telefono: '' };

function TabEmpleados({ empleados, areas }) {
    const [panel, setPanel] = useState(null); // null | { tipo: 'crear'|'editar', id: null|number }
    const [confirmarId, setConfirmarId] = useState(null);

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm(VACIO);

    const abrirCrear = () => {
        reset();
        clearErrors();
        setPanel({ tipo: 'crear', id: null });
    };

    const abrirEditar = (emp) => {
        setData({ area_id: emp.area_id ?? '', identificacion: emp.identificacion, nombres: emp.nombres, apellidos: emp.apellidos, email: emp.email ?? '', telefono: emp.telefono ?? '' });
        clearErrors();
        setPanel({ tipo: 'editar', id: emp.id });
    };

    const cancelar = () => { setPanel(null); clearErrors(); };

    const submit = (e) => {
        e.preventDefault();
        if (panel.tipo === 'crear') {
            post(route('parametros.empleados.store'), { onSuccess: () => setPanel(null) });
        } else {
            put(route('parametros.empleados.update', panel.id), { onSuccess: () => setPanel(null) });
        }
    };

    const eliminar = (emp) => {
        if (confirmarId !== emp.id) { setConfirmarId(emp.id); return; }
        router.delete(route('parametros.empleados.destroy', emp.id), { onSuccess: () => setConfirmarId(null) });
    };

    return (
        <div className="space-y-4">
            {/* Botón nuevo */}
            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={abrirCrear}
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors"
                >
                    <UserPlusIcon className="w-4 h-4" /> Nuevo empleado
                </button>
            </div>

            {/* Panel crear / editar */}
            {panel && (
                <div className="bg-white rounded-xl border border-indigo-200 p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-indigo-500 mb-4">
                        {panel.tipo === 'crear' ? 'Nuevo empleado' : 'Editar empleado'}
                    </p>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-3 gap-4">
                            <Field label="Identificación" error={errors.identificacion}>
                                <Input
                                    value={data.identificacion}
                                    onChange={(e) => setData('identificacion', e.target.value)}
                                    error={errors.identificacion}
                                    placeholder="CC / NIT"
                                />
                            </Field>
                            <Field label="Nombres" error={errors.nombres}>
                                <Input
                                    value={data.nombres}
                                    onChange={(e) => setData('nombres', e.target.value)}
                                    error={errors.nombres}
                                    placeholder="Nombres"
                                />
                            </Field>
                            <Field label="Apellidos" error={errors.apellidos}>
                                <Input
                                    value={data.apellidos}
                                    onChange={(e) => setData('apellidos', e.target.value)}
                                    error={errors.apellidos}
                                    placeholder="Apellidos"
                                />
                            </Field>
                            <Field label="Correo electrónico" error={errors.email}>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    error={errors.email}
                                    placeholder="correo@ejemplo.com"
                                />
                            </Field>
                            <Field label="Teléfono" error={errors.telefono}>
                                <Input
                                    value={data.telefono}
                                    onChange={(e) => setData('telefono', e.target.value)}
                                    error={errors.telefono}
                                    placeholder="3001234567"
                                />
                            </Field>
                            <Field label="Departamento" error={errors.area_id}>
                                <select
                                    value={data.area_id}
                                    onChange={(e) => setData('area_id', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none"
                                >
                                    <option value="">— Sin departamento —</option>
                                    {areas.map((a) => (
                                        <option key={a.id} value={a.id}>{a.nombre}</option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <div className="flex justify-end gap-3 pt-1">
                            <button
                                type="button"
                                onClick={cancelar}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
                            >
                                <XCircleIcon className="w-4 h-4" /> Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50"
                            >
                                <CheckCircleIcon className="w-4 h-4" />
                                {processing ? 'Guardando…' : panel.tipo === 'crear' ? 'Crear empleado' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Tabla */}
            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                {empleados.length === 0 ? (
                    <p className="text-sm text-slate-400 text-center py-10">No hay empleados registrados.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Identificación</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Nombres</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Apellidos</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Departamento</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Correo</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Teléfono</th>
                                <th className="px-4 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {empleados.map((emp) => (
                                <tr key={emp.id} className="hover:bg-slate-50/50">
                                    <td className="px-4 py-3 font-mono text-slate-700">{emp.identificacion}</td>
                                    <td className="px-4 py-3 text-slate-700">{emp.nombres}</td>
                                    <td className="px-4 py-3 text-slate-700">{emp.apellidos}</td>
                                    <td className="px-4 py-3 text-slate-500">{emp.area?.nombre ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-500">{emp.email ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-500">{emp.telefono ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                onClick={() => abrirEditar(emp)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                                                title="Editar"
                                            >
                                                <PencilSquareIcon className="w-4 h-4" />
                                            </button>
                                            {confirmarId === emp.id ? (
                                                <div className="flex items-center gap-1 ml-1">
                                                    <button
                                                        type="button"
                                                        onClick={() => eliminar(emp)}
                                                        className="px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg"
                                                    >
                                                        Confirmar
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => setConfirmarId(null)}
                                                        className="px-2 py-1 text-xs font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg"
                                                    >
                                                        No
                                                    </button>
                                                </div>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => eliminar(emp)}
                                                    className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                    title="Eliminar"
                                                >
                                                    <TrashIcon className="w-4 h-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

/* ─── Tab Contratos ───────────────────────────────────── */
const CONTRATO_VACIO = { descripcion: '', objeto: '', municipios: [] };

function TabContratos({ contratos, municipios }) {
    const [panel, setPanel] = useState(null); // null | { tipo, id }
    const [confirmarId, setConfirmarId] = useState(null);
    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm(CONTRATO_VACIO);

    const abrirCrear = () => { reset(); clearErrors(); setPanel({ tipo: 'crear', id: null }); };
    const abrirEditar = (c) => {
        setData({ descripcion: c.descripcion, objeto: c.objeto, municipios: c.municipios.map((m) => m.id) });
        clearErrors();
        setPanel({ tipo: 'editar', id: c.id });
    };
    const cancelar = () => { setPanel(null); clearErrors(); };

    const submit = (e) => {
        e.preventDefault();
        if (panel.tipo === 'crear') post(route('parametros.contratos.store'), { onSuccess: () => setPanel(null) });
        else put(route('parametros.contratos.update', panel.id), { onSuccess: () => setPanel(null) });
    };
    const eliminar = (c) => {
        if (confirmarId !== c.id) { setConfirmarId(c.id); return; }
        router.delete(route('parametros.contratos.destroy', c.id), { onSuccess: () => setConfirmarId(null) });
    };
    const toggleMunicipio = (id, checked) => {
        setData('municipios', checked ? [...data.municipios, id] : data.municipios.filter((x) => x !== id));
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <button type="button" onClick={abrirCrear}
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                    Nuevo contrato
                </button>
            </div>

            {panel && (
                <div className="bg-white rounded-xl border border-indigo-200 p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-indigo-500 mb-4">
                        {panel.tipo === 'crear' ? 'Nuevo contrato' : 'Editar contrato'}
                    </p>
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Descripción" error={errors.descripcion}>
                            <Input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} error={errors.descripcion} />
                        </Field>
                        <Field label="Objeto del contrato" error={errors.objeto}>
                            <textarea value={data.objeto} onChange={(e) => setData('objeto', e.target.value)} rows={3}
                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none" />
                            {errors.objeto && <p className="text-red-500 text-xs mt-1">{errors.objeto}</p>}
                        </Field>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Municipios</label>
                            <div className="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1">
                                {municipios.map((m) => (
                                    <label key={m.id} className="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" className="rounded border-slate-300 text-indigo-600"
                                            checked={data.municipios.includes(m.id)}
                                            onChange={(e) => toggleMunicipio(m.id, e.target.checked)} />
                                        {m.nombre}
                                    </label>
                                ))}
                            </div>
                            {errors.municipios && <p className="text-red-500 text-xs mt-1">{errors.municipios}</p>}
                        </div>
                        <div className="flex justify-end gap-3 pt-1">
                            <button type="button" onClick={cancelar}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button type="submit" disabled={processing}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50">
                                {processing ? 'Guardando…' : panel.tipo === 'crear' ? 'Crear contrato' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                {contratos.length === 0 ? (
                    <p className="text-sm text-slate-400 text-center py-10">No hay contratos registrados.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Descripción</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Objeto</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Municipios</th>
                                <th className="px-4 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {contratos.map((c) => (
                                <tr key={c.id} className="hover:bg-slate-50/50">
                                    <td className="px-4 py-3 text-slate-700">{c.descripcion}</td>
                                    <td className="px-4 py-3 text-slate-500">{c.objeto}</td>
                                    <td className="px-4 py-3 text-slate-500">{c.municipios.map((m) => m.nombre).join(', ') || '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <button type="button" onClick={() => abrirEditar(c)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Editar">✎</button>
                                            <button type="button" onClick={() => eliminar(c)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50" title="Eliminar">
                                                {confirmarId === c.id ? '¿Confirmar?' : '🗑'}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}

/* ─── Página principal ────────────────────────────────── */
const TABS = [
    { id: 'tarifas',   label: 'Tarifas de viáticos' },
    { id: 'empleados', label: 'Empleados' },
    { id: 'contratos', label: 'Contratos' },
];

export default function Index({ tarifas, empleados, areas = [], contratos = [], municipios = [] }) {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [tab, setTab] = useState('tarifas');

    return (
        <AppLayout title="Parámetros">
            <Head title="Parámetros" />
            <div className="p-6 w-full space-y-5">

                {/* Flash */}
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
                <div>
                    <h2 className="text-lg font-semibold text-slate-800">Parámetros del sistema</h2>
                    <p className="text-sm text-slate-500 mt-0.5">
                        Configure las tarifas de viáticos y los empleados disponibles para comisiones.
                    </p>
                </div>

                {/* Tabs */}
                <div className="border-b border-slate-200 flex gap-1">
                    {TABS.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            onClick={() => setTab(t.id)}
                            className={[
                                'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px',
                                tab === t.id
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            ].join(' ')}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                {/* Contenido del tab */}
                {tab === 'tarifas'   && <TabTarifas   tarifas={tarifas} />}
                {tab === 'empleados' && <TabEmpleados empleados={empleados} areas={areas} />}
                {tab === 'contratos' && <TabContratos contratos={contratos} municipios={municipios} />}

            </div>
        </AppLayout>
    );
}
