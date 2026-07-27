import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircleIcon, PencilSquareIcon, UserPlusIcon, XCircleIcon } from '@heroicons/react/24/outline';

const etiquetaRol = (r) =>
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

const VACIO = { name: '', email: '', password: '', password_confirmation: '', roles: [] };

export default function Index({ usuarios, roles }) {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [panel, setPanel] = useState(null); // null | { tipo: 'crear'|'editar', id: null|number }

    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm(VACIO);

    const abrirCrear = () => {
        reset();
        clearErrors();
        setPanel({ tipo: 'crear', id: null });
    };

    const abrirEditar = (u) => {
        setData({
            name: u.name,
            email: u.email,
            password: '',
            password_confirmation: '',
            roles: u.roles.map((r) => r.name),
        });
        clearErrors();
        setPanel({ tipo: 'editar', id: u.id });
    };

    const cancelar = () => { setPanel(null); clearErrors(); };

    const toggleRol = (rol) => {
        setData('roles', data.roles.includes(rol)
            ? data.roles.filter((r) => r !== rol)
            : [...data.roles, rol]);
    };

    const submit = (e) => {
        e.preventDefault();
        if (panel.tipo === 'crear') {
            post(route('usuarios.store'), { onSuccess: () => setPanel(null) });
        } else {
            put(route('usuarios.update', panel.id), { onSuccess: () => setPanel(null) });
        }
    };

    const editando = panel?.tipo === 'editar';

    return (
        <AppLayout title="Usuarios">
            <Head title="Usuarios" />
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
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-800">Gestión de usuarios</h2>
                        <p className="text-sm text-slate-500 mt-0.5">
                            Cree usuarios, edite sus datos y asigne los roles del sistema.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={abrirCrear}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors"
                    >
                        <UserPlusIcon className="w-4 h-4" /> Nuevo usuario
                    </button>
                </div>

                {/* Panel crear / editar */}
                {panel && (
                    <div className="bg-white rounded-xl border border-indigo-200 p-5">
                        <p className="text-xs font-semibold uppercase tracking-wide text-indigo-500 mb-4">
                            {editando ? 'Editar usuario' : 'Nuevo usuario'}
                        </p>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Nombre" error={errors.name}>
                                    <Input
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        error={errors.name}
                                        placeholder="Nombre completo"
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
                                <Field
                                    label={editando ? 'Contraseña (dejar en blanco para no cambiar)' : 'Contraseña temporal'}
                                    error={errors.password}
                                >
                                    <Input
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        error={errors.password}
                                        placeholder={editando ? '••••••••' : 'Mínimo 8 caracteres'}
                                        autoComplete="new-password"
                                    />
                                </Field>
                                <Field label="Confirmar contraseña" error={errors.password_confirmation}>
                                    <Input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        placeholder="Repita la contraseña"
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>

                            {/* Selector de roles (checkboxes) */}
                            <Field label="Roles" error={errors.roles}>
                                <div className="grid grid-cols-3 gap-2 mt-1">
                                    {roles.map((rol) => (
                                        <label
                                            key={rol}
                                            className={[
                                                'flex items-center gap-2 px-3 py-2 rounded-lg border text-sm cursor-pointer transition-colors',
                                                data.roles.includes(rol)
                                                    ? 'border-indigo-300 bg-indigo-50 text-indigo-700'
                                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                            ].join(' ')}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={data.roles.includes(rol)}
                                                onChange={() => toggleRol(rol)}
                                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            {etiquetaRol(rol)}
                                        </label>
                                    ))}
                                </div>
                            </Field>

                            <div className="flex justify-end gap-3 pt-1">
                                <button type="button" onClick={cancelar}
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                                    <XCircleIcon className="w-4 h-4" /> Cancelar
                                </button>
                                <button type="submit" disabled={processing}
                                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50">
                                    <CheckCircleIcon className="w-4 h-4" />
                                    {processing ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear usuario'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tabla */}
                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    {usuarios.length === 0 ? (
                        <p className="text-sm text-slate-400 text-center py-10">No hay usuarios registrados.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 border-b border-slate-100">
                                <tr>
                                    <th className="text-left text-xs font-semibold text-slate-500 px-5 py-3">Nombre</th>
                                    <th className="text-left text-xs font-semibold text-slate-500 px-5 py-3">Correo</th>
                                    <th className="text-left text-xs font-semibold text-slate-500 px-5 py-3">Roles</th>
                                    <th className="px-5 py-3 w-16"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {usuarios.map((u) => (
                                    <tr key={u.id} className="hover:bg-slate-50/50">
                                        <td className="px-5 py-3 font-medium text-slate-700">{u.name}</td>
                                        <td className="px-5 py-3 text-slate-500">{u.email}</td>
                                        <td className="px-5 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {u.roles.length === 0 ? (
                                                    <span className="text-slate-400">—</span>
                                                ) : (
                                                    u.roles.map((r) => (
                                                        <span key={r.id ?? r.name}
                                                            className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                            {etiquetaRol(r.name)}
                                                        </span>
                                                    ))
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center justify-end">
                                                <button type="button" onClick={() => abrirEditar(u)}
                                                    className="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Editar">
                                                    <PencilSquareIcon className="w-4 h-4" />
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
        </AppLayout>
    );
}
