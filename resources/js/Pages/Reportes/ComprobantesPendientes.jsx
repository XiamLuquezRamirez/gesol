import { Link } from '@inertiajs/react';
import VistaInforme, { Tarjeta, Vacio } from './Componentes/VistaInforme';

export default function ComprobantesPendientes({ filtros, reporte }) {
    return (
        <VistaInforme
            titulo="Comprobantes pendientes"
            subtitulo="Comisiones cerradas en el rango cuyos viajeros no tienen comprobante de transferencia adjunto."
            ruta="reportes.comprobantes"
            filtros={filtros}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 mb-2">
                <Tarjeta label="Comisiones con faltantes" valor={reporte.comisiones.length} />
            </div>

            {reporte.comisiones.length === 0 ? (
                <Vacio>Todas las comisiones cerradas del rango tienen su comprobante. </Vacio>
            ) : (
                <div className="space-y-2">
                    {reporte.comisiones.map((c) => (
                        <div key={c.solicitud_id} className="rounded-lg border border-amber-100 bg-amber-50/50 px-4 py-3">
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <span className="text-xs font-mono text-slate-400">{c.radicado}</span>
                                    <p className="text-sm font-medium text-slate-800 truncate">{c.nombre}</p>
                                </div>
                                <Link href={route('solicitudes.show', c.solicitud_id)}
                                    className="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                    Ver solicitud →
                                </Link>
                            </div>
                            <p className="mt-1 text-xs text-slate-600">
                                Sin comprobante: <span className="font-medium">{c.sin_comprobante.join(', ')}</span>
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </VistaInforme>
    );
}
