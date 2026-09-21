import { formatearMoneda } from '@/lib/format';
import VistaInforme, { Tarjeta, Vacio } from './Componentes/VistaInforme';

export default function ReporteOficina({ filtros, reporte }) {
    return (
        <VistaInforme
            titulo="Oficina: aprobado vs. pagado"
            subtitulo="Pagos (abonos) registrados dentro del rango, agrupados por solicitud."
            ruta="reportes.oficina"
            exportable
            filtros={filtros}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-2">
                <Tarjeta label="Total aprobado" valor={formatearMoneda(reporte.total_aprobado)} />
                <Tarjeta label="Pagado en el rango" valor={formatearMoneda(reporte.total_pagado)} />
                <Tarjeta label="Saldo pendiente" valor={formatearMoneda(reporte.saldo_total)} />
                <Tarjeta label="Solicitudes" valor={reporte.num_solicitudes} />
            </div>

            {reporte.solicitudes.length === 0 ? (
                <Vacio>No hay pagos de oficina en este rango.</Vacio>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-slate-100">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-3 py-2 font-medium">Radicado</th>
                                <th className="px-3 py-2 font-medium">Solicitante</th>
                                <th className="px-3 py-2 font-medium">Área</th>
                                <th className="px-3 py-2 font-medium text-right">Total a pagar</th>
                                <th className="px-3 py-2 font-medium text-right">Pagado (rango)</th>
                                <th className="px-3 py-2 font-medium text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {reporte.solicitudes.map((s) => (
                                <tr key={s.radicado}>
                                    <td className="px-3 py-2 font-mono text-xs text-slate-500">{s.radicado}</td>
                                    <td className="px-3 py-2 text-slate-700">{s.solicitante}</td>
                                    <td className="px-3 py-2 text-slate-600">{s.area}</td>
                                    <td className="px-3 py-2 text-right text-slate-700">{s.total !== null ? formatearMoneda(s.total) : '—'}</td>
                                    <td className="px-3 py-2 text-right font-medium text-slate-800">{formatearMoneda(s.pagado_rango)}</td>
                                    <td className="px-3 py-2 text-right text-slate-600">{formatearMoneda(s.saldo)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </VistaInforme>
    );
}
