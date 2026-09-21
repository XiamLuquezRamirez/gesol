import VistaInforme, { Tarjeta, Vacio } from './Componentes/VistaInforme';

export default function ReportePersonal({ filtros, reporte }) {
    return (
        <VistaInforme
            titulo="Personal en comisión"
            subtitulo="Empleados que estuvieron por fuera en el rango (por fecha de comisión)."
            ruta="reportes.personal"
            exportable
            filtros={filtros}
        >
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 mb-2">
                <Tarjeta label="Empleados" valor={reporte.num_empleados} />
                <Tarjeta label="Días-persona" valor={reporte.total_dias} />
            </div>

            {reporte.empleados.length === 0 ? (
                <Vacio>No hay personal en comisión en este rango.</Vacio>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-slate-100">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100 text-left text-xs text-slate-500">
                            <tr>
                                <th className="px-3 py-2 font-medium">Empleado</th>
                                <th className="px-3 py-2 font-medium">Área</th>
                                <th className="px-3 py-2 font-medium text-center">Comisiones</th>
                                <th className="px-3 py-2 font-medium text-center">Días</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {reporte.empleados.map((e, i) => (
                                <tr key={i}>
                                    <td className="px-3 py-2 text-slate-700">{e.empleado}</td>
                                    <td className="px-3 py-2 text-slate-600">{e.area}</td>
                                    <td className="px-3 py-2 text-center text-slate-700">{e.num_comisiones}</td>
                                    <td className="px-3 py-2 text-center font-medium text-slate-800">{e.dias}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </VistaInforme>
    );
}
