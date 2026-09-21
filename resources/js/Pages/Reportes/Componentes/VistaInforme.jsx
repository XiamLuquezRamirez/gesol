import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import FiltroRango from './FiltroRango';

/**
 * Envoltura comun de una vista de informe: cabecera con titulo, enlace "Volver a
 * reportes", y el filtro de rango (que reconsulta la propia ruta del informe).
 * `ruta` es el nombre de ruta del informe para reconsultar con el nuevo rango.
 */
export default function VistaInforme({ titulo, subtitulo, ruta, filtros, exportable = false, children }) {
    const [desde, setDesde] = useState(filtros?.desde ?? '');
    const [hasta, setHasta] = useState(filtros?.hasta ?? '');

    useEffect(() => {
        setDesde(filtros?.desde ?? '');
        setHasta(filtros?.hasta ?? '');
    }, [filtros]);

    const consultar = (d, h) => {
        router.get(route(ruta),
            { desde: d || undefined, hasta: h || undefined },
            { preserveState: true, replace: true });
    };

    // URLs de exportacion: la misma ruta del informe con ?export=xlsx|pdf y el rango.
    const urlExport = (formato) => route(ruta, { desde: filtros?.desde, hasta: filtros?.hasta, export: formato });

    return (
        <AppLayout title={titulo}>
            <Head title={titulo} />
            <div className="p-6 w-full space-y-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Link href={route('reportes.index', { desde: filtros?.desde, hasta: filtros?.hasta })}
                            className="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                            ← Volver a reportes
                        </Link>
                        <h2 className="mt-1 text-lg font-semibold text-slate-800">{titulo}</h2>
                        {subtitulo && <p className="text-sm text-slate-500 mt-0.5">{subtitulo}</p>}
                    </div>
                    {exportable && (
                        <div className="flex shrink-0 gap-2">
                            <a href={urlExport('xlsx')}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100">
                                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 1 2-2h6.586A2 2 0 0 1 13 2.586L16.414 6A2 2 0 0 1 17 7.414V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4Z" /></svg>
                                Excel
                            </a>
                            <a href={urlExport('pdf')}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">
                                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a2 2 0 0 1 2-2h6.586A2 2 0 0 1 13 2.586L16.414 6A2 2 0 0 1 17 7.414V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4Z" /></svg>
                                PDF
                            </a>
                        </div>
                    )}
                </div>

                <FiltroRango
                    desde={desde} hasta={hasta}
                    setDesde={setDesde} setHasta={setHasta}
                    onConsultar={consultar}
                />

                {children}
            </div>
        </AppLayout>
    );
}

/** Tarjeta de indicador (numero grande + etiqueta). */
export function Tarjeta({ label, valor, sub }) {
    return (
        <div className="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
            <p className="text-xs font-medium text-slate-500">{label}</p>
            <p className="mt-1 text-xl font-semibold text-slate-800">{valor}</p>
            {sub && <p className="text-xs text-slate-400 mt-0.5">{sub}</p>}
        </div>
    );
}

export function Vacio({ children }) {
    return <p className="text-sm text-slate-400 text-center py-10">{children}</p>;
}
