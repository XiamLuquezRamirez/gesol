import { Link } from '@inertiajs/react';
import { formatearMoneda } from '@/lib/format';
import VistaInforme, { Tarjeta, Vacio } from './Componentes/VistaInforme';

const ETIQUETAS_ESTADO = {
    pendiente_liquidacion: 'Pendiente de liquidación',
    liquidado: 'Liquidado',
    aprobado: 'Aprobado',
    devuelto: 'Devuelto',
};
const ETIQUETAS_TIPO = { fechas: 'Fechas', rubro: 'Rubro' };

export default function Reajustes({ filtros, reporte }) {
    return (
        <VistaInforme
            titulo="Reajustes (ajustes post-cierre)"
            subtitulo="Ajustes sobre comisiones del rango, con su valor delta (mayor o menor liquidación)."
            ruta="reportes.reajustes"
            filtros={filtros}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 mb-2">
                <Tarjeta label="Reajustes" valor={reporte.num} />
                <Tarjeta label="Delta total" valor={formatearMoneda(reporte.total_delta)} />
            </div>

            {reporte.ajustes.length === 0 ? (
                <Vacio>No hay reajustes en este rango.</Vacio>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-slate-100">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-3 py-2 font-medium">Radicado</th>
                                <th className="px-3 py-2 font-medium">Viajero</th>
                                <th className="px-3 py-2 font-medium">Tipo</th>
                                <th className="px-3 py-2 font-medium">Estado</th>
                                <th className="px-3 py-2 font-medium text-right">Delta</th>
                                <th className="px-3 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {reporte.ajustes.map((a) => (
                                <tr key={a.id}>
                                    <td className="px-3 py-2 font-mono text-xs text-slate-500">{a.radicado}</td>
                                    <td className="px-3 py-2 text-slate-700">{a.viajero}</td>
                                    <td className="px-3 py-2 text-slate-600">{ETIQUETAS_TIPO[a.tipo] ?? a.tipo}</td>
                                    <td className="px-3 py-2 text-slate-600">{ETIQUETAS_ESTADO[a.estado] ?? a.estado}</td>
                                    <td className="px-3 py-2 text-right font-medium text-slate-800">{formatearMoneda(a.delta)}</td>
                                    <td className="px-3 py-2 text-right">
                                        <Link href={route('solicitudes.show', a.solicitud_id)}
                                            className="text-xs font-medium text-indigo-600 hover:text-indigo-700">
                                            Ver →
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </VistaInforme>
    );
}
