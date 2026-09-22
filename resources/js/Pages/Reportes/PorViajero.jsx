import { formatearMoneda } from '@/lib/format';
import { useState } from 'react';
import VistaInforme, { Tarjeta, Vacio } from './Componentes/VistaInforme';

const ETIQUETAS_RUBRO = {
    desayuno: 'Desayuno', almuerzo: 'Almuerzo', cena: 'Cena',
    merienda: 'Merienda', gasolina: 'Gasolina', transporte: 'Transporte',
};
const etiquetaRubro = (r) => ETIQUETAS_RUBRO[r] ?? r;

/** Fila expandible de una comisión: al abrir muestra rubros y comprobantes de pago. */
function FilaComision({ comision }) {
    const [abierta, setAbierta] = useState(false);

    return (
        <div className="rounded-lg border border-slate-100">
            <button
                type="button"
                onClick={() => setAbierta((v) => !v)}
                className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left hover:bg-slate-50"
            >
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-mono text-slate-400">{comision.radicado}</span>
                    </div>
                    <p className="text-sm font-medium text-slate-800 truncate">{comision.nombre}</p>
                </div>
                <div className="flex items-center gap-3 shrink-0">
                    <span className="text-sm font-semibold text-slate-800">{formatearMoneda(comision.total)}</span>
                    <svg className={`h-4 w-4 text-slate-400 transition-transform ${abierta ? 'rotate-180' : ''}`}
                        viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clipRule="evenodd" />
                    </svg>
                </div>
            </button>

            {abierta && (
                <div className="border-t border-slate-100 px-4 py-3">
                    {/* Rubros de la comisión */}
                    {comision.rubros.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {comision.rubros.map((r, j) => (
                                <span key={j} className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-xs text-slate-600 border border-slate-100">
                                    {etiquetaRubro(r.rubro)}: {formatearMoneda(r.subtotal)}
                                </span>
                            ))}
                        </div>
                    )}

                    {/* Comprobantes de pago */}
                    <div className="mt-2">
                        <p className="text-xs font-medium text-slate-500 mb-1">
                            Comprobantes de pago ({comision.comprobantes.length})
                        </p>
                        {comision.comprobantes.length === 0 ? (
                            <p className="text-xs text-amber-600">Sin comprobante adjunto.</p>
                        ) : (
                            <ul className="space-y-1">
                                {comision.comprobantes.map((c) => (
                                    <li key={c.id}>
                                        <a href={c.url}
                                            className="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-700">
                                            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                                                <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                                            </svg>
                                            {c.nombre}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

/** Tarjeta por viajero: cabecera con nombre/área/id + total, y sus comisiones desplegables. */
function CardViajero({ viajero }) {
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
                <div className="text-right shrink-0">
                    <p className="text-lg font-semibold text-slate-800">{formatearMoneda(viajero.total)}</p>
                    <p className="text-xs text-slate-500">{viajero.num_comisiones} comisión(es)</p>
                </div>
            </div>

            <div className="mt-3 space-y-2">
                {viajero.comisiones.map((c, i) => (
                    <FilaComision key={c.solicitud_id ?? i} comision={c} />
                ))}
            </div>
        </div>
    );
}

export default function ReportePorViajero({ filtros, reporte }) {
    return (
        <VistaInforme
            titulo="Gasto por viajero"
            subtitulo="Total de comisiones por empleado (rango por fecha de comisión). Abre cada comisión para ver gasto y comprobantes."
            ruta="reportes.por-viajero"
            exportable
            filtros={filtros}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 mb-2">
                <Tarjeta label="Total viáticos" valor={formatearMoneda(reporte.total)} />
                <Tarjeta label="Empleados" valor={reporte.num_empleados} />
                <Tarjeta label="Comisiones" valor={reporte.num_comisiones} />
            </div>

            {reporte.viajeros.length === 0 ? (
                <Vacio>No hay viajeros en este rango.</Vacio>
            ) : (
                <div className="space-y-3">
                    {reporte.viajeros.map((v, i) => (
                        <CardViajero key={i} viajero={v} />
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
