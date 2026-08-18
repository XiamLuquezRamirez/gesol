import { useMemo, useState } from 'react';

/**
 * Multiselect con buscador y chips, sin dependencias externas.
 *
 * Props:
 * - opciones: [{ id, nombre }]  — el catálogo completo.
 * - seleccionados: number[]     — ids seleccionados.
 * - onChange: (ids) => void      — recibe el nuevo array de ids.
 * - placeholder?: string         — texto del buscador.
 * - vacio?: string               — mensaje cuando no hay opciones.
 */
export default function MultiSelectBuscador({
    opciones = [],
    seleccionados = [],
    onChange,
    placeholder = 'Buscar…',
    vacio = 'No hay opciones registradas.',
}) {
    const [busqueda, setBusqueda] = useState('');

    // Normaliza para buscar ignorando mayúsculas y tildes.
    const normalizar = (s) =>
        (s ?? '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    const filtradas = useMemo(() => {
        const q = normalizar(busqueda).trim();
        if (!q) return opciones;
        return opciones.filter((o) => normalizar(o.nombre).includes(q));
    }, [opciones, busqueda]);

    const elegidas = useMemo(
        () => opciones.filter((o) => seleccionados.includes(o.id)),
        [opciones, seleccionados]
    );

    const toggle = (id, checked) => {
        onChange(checked ? [...seleccionados, id] : seleccionados.filter((x) => x !== id));
    };

    return (
        <div className="space-y-2">
            {/* Chips de seleccionados */}
            {elegidas.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {elegidas.map((o) => (
                        <span key={o.id}
                            className="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                            {o.nombre}
                            <button type="button"
                                onClick={() => toggle(o.id, false)}
                                className="rounded-full w-4 h-4 flex items-center justify-center text-indigo-400 hover:text-indigo-700 hover:bg-indigo-100"
                                title="Quitar">
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}

            {/* Buscador */}
            <input
                type="text"
                value={busqueda}
                onChange={(e) => setBusqueda(e.target.value)}
                placeholder={placeholder}
                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none"
            />

            {/* Lista filtrada */}
            <div className="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1">
                {opciones.length === 0 ? (
                    <p className="text-xs text-slate-400">{vacio}</p>
                ) : filtradas.length === 0 ? (
                    <p className="text-xs text-slate-400">Sin coincidencias para «{busqueda}».</p>
                ) : (
                    filtradas.map((o) => (
                        <label key={o.id} className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                className="rounded border-slate-300 text-indigo-600"
                                checked={seleccionados.includes(o.id)}
                                onChange={(ev) => toggle(o.id, ev.target.checked)}
                            />
                            {o.nombre}
                        </label>
                    ))
                )}
            </div>
        </div>
    );
}
