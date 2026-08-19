import { useEffect, useMemo, useRef, useState } from 'react';

/**
 * Multiselect estilo "select2": buscas escribiendo dentro del propio campo,
 * los elegidos aparecen como chips dentro del campo, y las opciones se
 * despliegan en un dropdown flotante que permanece abierto mientras eliges
 * (se cierra al hacer clic fuera o con Escape). Sin dependencias externas.
 *
 * Props:
 * - opciones: [{ id, nombre }]  — el catálogo completo.
 * - seleccionados: number[]     — ids seleccionados.
 * - onChange: (ids) => void      — recibe el nuevo array de ids.
 * - placeholder?: string         — texto guía del buscador.
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
    const [abierto, setAbierto] = useState(false);
    const contenedorRef = useRef(null);
    const inputRef = useRef(null);

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

    // Cerrar el dropdown al hacer clic fuera del componente.
    useEffect(() => {
        if (!abierto) return;
        const alClicFuera = (e) => {
            if (contenedorRef.current && !contenedorRef.current.contains(e.target)) {
                setAbierto(false);
            }
        };
        document.addEventListener('mousedown', alClicFuera);
        return () => document.removeEventListener('mousedown', alClicFuera);
    }, [abierto]);

    const toggle = (id) => {
        onChange(
            seleccionados.includes(id)
                ? seleccionados.filter((x) => x !== id)
                : [...seleccionados, id]
        );
        // El dropdown permanece abierto para elegir varios seguidos.
        setBusqueda('');
        inputRef.current?.focus();
    };

    const quitar = (id) => onChange(seleccionados.filter((x) => x !== id));

    const alTeclado = (e) => {
        if (e.key === 'Escape') {
            setAbierto(false);
        } else if (e.key === 'Backspace' && busqueda === '' && elegidas.length > 0) {
            // Backspace con el buscador vacío quita el último chip.
            quitar(elegidas[elegidas.length - 1].id);
        }
    };

    return (
        <div ref={contenedorRef} className="relative">
            {/* Campo con chips + buscador inline */}
            <div
                onClick={() => { setAbierto(true); inputRef.current?.focus(); }}
                className="min-h-[42px] w-full flex flex-wrap items-center gap-1.5 rounded-lg border border-slate-300 bg-white text-sm py-1.5 px-2 cursor-text focus-within:ring-2 focus-within:ring-indigo-500"
            >
                {elegidas.map((o) => (
                    <span key={o.id}
                        className="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {o.nombre}
                        <button type="button"
                            onClick={(e) => { e.stopPropagation(); quitar(o.id); }}
                            className="rounded-full w-4 h-4 flex items-center justify-center text-indigo-400 hover:text-indigo-700 hover:bg-indigo-100"
                            title="Quitar">
                            ×
                        </button>
                    </span>
                ))}
                <input
                    ref={inputRef}
                    type="text"
                    value={busqueda}
                    onChange={(e) => { setBusqueda(e.target.value); setAbierto(true); }}
                    onFocus={() => setAbierto(true)}
                    onKeyDown={alTeclado}
                    placeholder={elegidas.length === 0 ? placeholder : ''}
                    className="flex-1 min-w-[8ch] border-0 p-0.5 text-sm outline-none focus:ring-0 placeholder:text-slate-400"
                />
            </div>

            {/* Dropdown flotante de opciones */}
            {abierto && (
                <div className="absolute z-20 mt-1 w-full rounded-lg border border-slate-300 bg-white shadow-lg max-h-56 overflow-y-auto py-1">
                    {opciones.length === 0 ? (
                        <p className="px-3 py-2 text-xs text-slate-400">{vacio}</p>
                    ) : filtradas.length === 0 ? (
                        <p className="px-3 py-2 text-xs text-slate-400">Sin coincidencias para «{busqueda}».</p>
                    ) : (
                        filtradas.map((o) => {
                            const activo = seleccionados.includes(o.id);
                            return (
                                <button
                                    key={o.id}
                                    type="button"
                                    onClick={() => toggle(o.id)}
                                    className={[
                                        'w-full flex items-center gap-2 px-3 py-1.5 text-left text-sm',
                                        activo ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50',
                                    ].join(' ')}
                                >
                                    <span className={[
                                        'w-4 h-4 flex items-center justify-center rounded border text-[10px]',
                                        activo ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300',
                                    ].join(' ')}>
                                        {activo ? '✓' : ''}
                                    </span>
                                    {o.nombre}
                                </button>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
}
