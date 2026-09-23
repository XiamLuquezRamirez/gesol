import { formatearMoneda } from '@/lib/format';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import VistaInforme, { Tarjeta, Vacio } from './Componentes/VistaInforme';

const ETIQUETAS_RUBRO = {
    desayuno: 'Desayuno', almuerzo: 'Almuerzo', cena: 'Cena',
    merienda: 'Merienda', gasolina: 'Gasolina', transporte: 'Transporte',
};
const etiquetaRubro = (r) => ETIQUETAS_RUBRO[r] ?? r;

/** Fila expandible de un rubro: al abrir muestra las comisiones que aportan y sus comprobantes. */
function FilaRubro({ rubro }) {
    const [abierta, setAbierta] = useState(false);

    return (
        <div className="rounded-lg border border-slate-100">
            <button
                type="button"
                onClick={() => setAbierta((v) => !v)}
                className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left hover:bg-slate-50"
            >
                <p className="text-sm font-medium text-slate-800 truncate">{etiquetaRubro(rubro.rubro)}</p>
                <div className="flex items-center gap-3 shrink-0">
                    <span className="text-sm font-semibold text-slate-800">{formatearMoneda(rubro.total)}</span>
                    <svg className={`h-4 w-4 text-slate-400 transition-transform ${abierta ? 'rotate-180' : ''}`}
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                    </svg>
                </div>
            </button>

            {abierta && (
                <div className="border-t border-slate-100 px-4 py-3 space-y-3">
                    {rubro.comisiones.map((c, i) => (
                        <div key={c.solicitud_id ?? i} className="rounded-md bg-slate-50 px-3 py-2">
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <span className="text-xs font-mono text-slate-400">{c.radicado}</span>
                                    <p className="text-sm font-medium text-slate-800 truncate">{c.nombre}</p>
                                </div>
                                <span className="text-sm font-semibold text-slate-800 shrink-0">{formatearMoneda(c.subtotal)}</span>
                            </div>
                            <div className="mt-2">
                                <p className="text-xs font-medium text-slate-500 mb-1">
                                    Comprobantes de pago ({c.comprobantes.length})
                                </p>
                                {c.comprobantes.length === 0 ? (
                                    <p className="text-xs text-amber-600">Sin comprobante adjunto.</p>
                                ) : (
                                    <ul className="space-y-1">
                                        {c.comprobantes.map((cp) => (
                                            <li key={cp.id}>
                                                <a href={cp.url}
                                                    className="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-700">
                                                    <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                                                        <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                                                    </svg>
                                                    {cp.nombre}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/** Tarjeta por viajero: cabecera con nombre/área/id + total + botones de impresión, y sus rubros. */
function CardViajero({ viajero, filtros }) {
    const query = { desde: filtros?.desde, hasta: filtros?.hasta };

    return (
        <div className="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-slate-800 truncate">{viajero.empleado}</p>
                    <p className="text-xs text-slate-500">
                        {viajero.area}
                        {viajero.identificacion ? ` · ${viajero.identificacion}` : ''}
                    </p>
                </div>
                <div className="flex items-start gap-3 shrink-0">
                    {viajero.empleado_id != null && (
                        <div className="flex gap-2">
                            <a href={route('reportes.por-viajero', { ...query, empleado: viajero.empleado_id, export: 'pdf' })}
                                className="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 1 2-2h6.586A2 2 0 0 1 13 2.586L16.414 6A2 2 0 0 1 17 7.414V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4Z" /></svg>
                                PDF
                            </a>
                            <a href={route('reportes.por-viajero', { ...query, empleado: viajero.empleado_id, export: 'xlsx' })}
                                className="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                                <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 1 2-2h6.586A2 2 0 0 1 13 2.586L16.414 6A2 2 0 0 1 17 7.414V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4Z" /></svg>
                                Excel
                            </a>
                        </div>
                    )}
                    <div className="text-right">
                        <p className="text-lg font-semibold text-slate-800">{formatearMoneda(viajero.total)}</p>
                        <p className="text-xs text-slate-500">{viajero.rubros.length} rubro(s)</p>
                    </div>
                </div>
            </div>

            <div className="mt-3 space-y-2">
                {viajero.rubros.map((r, i) => (
                    <FilaRubro key={r.rubro ?? i} rubro={r} />
                ))}
            </div>
        </div>
    );
}

/** Selector de empleado: filtra el reporte por empleado (o todos si está vacío). */
function FiltroEmpleado({ filtros, empleados }) {
    const cambiar = (value) => {
        // preserveState:false fuerza a Inertia a tomar los props nuevos (reporte
        // ya filtrado) del backend; preserveScroll evita saltar al inicio. Sin
        // esto, el estado del componente podia conservar el listado anterior y
        // "seguir mostrando" todos los empleados tras filtrar.
        router.get(route('reportes.por-viajero'),
            { desde: filtros?.desde, hasta: filtros?.hasta, empleado: value || undefined },
            { preserveState: false, preserveScroll: true, replace: true });
    };

    return (
        <div className="flex flex-wrap items-center gap-3 rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
            <label className="text-sm font-medium text-slate-600">Empleado:</label>
            <select
                value={filtros?.empleado != null ? String(filtros.empleado) : ''}
                onChange={(e) => cambiar(e.target.value)}
                className="rounded-lg border-slate-200 text-sm"
            >
                <option value="">Todos los empleados</option>
                {empleados.map((e) => (
                    <option key={e.id} value={String(e.id)}>{e.nombre}</option>
                ))}
            </select>
        </div>
    );
}

export default function ReportePorViajero({ filtros, reporte, empleados = [] }) {
    return (
        <VistaInforme
            titulo="Gasto por viajero"
            subtitulo="Gasto por empleado agrupado por rubro. Abre un rubro para ver comisiones y comprobantes."
            ruta="reportes.por-viajero"
            filtros={filtros}
        >
            <FiltroEmpleado filtros={filtros} empleados={empleados} />

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 mb-2">
                <Tarjeta label="Total viáticos" valor={formatearMoneda(reporte.total)} />
                <Tarjeta label="Empleados" valor={reporte.num_empleados} />
                <Tarjeta label="Rubros" valor={reporte.num_rubros} />
            </div>

            {reporte.viajeros.length === 0 ? (
                <Vacio>No hay viajeros en este rango.</Vacio>
            ) : (
                <div className="space-y-3">
                    {reporte.viajeros.map((v, i) => (
                        <CardViajero key={v.empleado_id ?? i} viajero={v} filtros={filtros} />
                    ))}
                    <div className="flex items-center justify-between rounded-lg bg-slate-800 px-4 py-3 text-white">
                        <span className="text-sm font-medium">Total general</span>
                        <span className="text-base font-semibold">{formatearMoneda(reporte.total)}</span>
                    </div>
                </div>
            )}
        </VistaInforme>
    );
}
