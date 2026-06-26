import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import BadgeEstado from '@/Components/BadgeEstado';
import { formatearMoneda } from '@/lib/format';
import { useForm } from '@inertiajs/react';

const ETIQUETAS_RUBRO = {
    desayuno:'Desayuno', almuerzo:'Almuerzo', cena:'Cena', merienda:'Merienda', gasolina:'Gasolina',
};

export default function Liquidacion({ solicitud, tarifas, rubros }) {
    const viajeros = solicitud.solicitable?.viajeros ?? [];

    const asignacionesIniciales = viajeros.flatMap((v) =>
        rubros.map((rubro) => {
            const exist = v.asignaciones?.find((a) => a.rubro === rubro);
            return {
                viajero_comision_id: v.id,
                rubro,
                valor_unitario: exist?.valor_unitario ?? tarifas[rubro]?.valor_sugerido ?? 0,
                dias: exist?.dias ?? 1,
            };
        })
    );

    const { data, setData, put, processing, errors } = useForm({
        asignaciones: asignacionesIniciales,
    });

    const actualizarAsignacion = (viajeroId, rubro, campo, valor) => {
        setData('asignaciones', data.asignaciones.map((a) =>
            a.viajero_comision_id === viajeroId && a.rubro === rubro ? { ...a, [campo]: valor } : a
        ));
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('viaticos.asignaciones', solicitud.id));
    };

    return (
        <AppLayout title={`Liquidación ${solicitud.radicado}`}>
            <div className="p-6 max-w-5xl mx-auto w-full space-y-5">
                <div className="flex items-center gap-3 mb-2">
                    <span className="font-mono text-base font-semibold text-slate-700">{solicitud.radicado}</span>
                    <BadgeEstado estado={solicitud.estado} />
                </div>

                <form onSubmit={submit} className="space-y-5">
                    {viajeros.map((viajero) => {
                        const asigs = data.asignaciones.filter((a) => a.viajero_comision_id === viajero.id);
                        const subtotal = asigs.reduce((acc, a) => acc + (Number(a.valor_unitario) * Number(a.dias)), 0);

                        return (
                            <div key={viajero.id} className="bg-white rounded-xl border border-slate-200 p-5">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-sm font-semibold text-slate-700">{viajero.usuario?.name}</h3>
                                    <span className="text-sm font-semibold text-slate-800">{formatearMoneda(subtotal)}</span>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-xs text-slate-500 border-b border-slate-100">
                                                <th className="pb-2 font-medium">Rubro</th>
                                                <th className="pb-2 font-medium">Valor unitario</th>
                                                <th className="pb-2 font-medium">Días</th>
                                                <th className="pb-2 font-medium text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50">
                                            {rubros.map((rubro) => {
                                                const a = asigs.find((x) => x.rubro === rubro);
                                                if (!a) return null;
                                                return (
                                                    <tr key={rubro}>
                                                        <td className="py-2 text-slate-600">{ETIQUETAS_RUBRO[rubro]}</td>
                                                        <td className="py-2 w-40">
                                                            <CampoMoneda
                                                                value={a.valor_unitario}
                                                                onChange={(v) => actualizarAsignacion(viajero.id, rubro, 'valor_unitario', v)}
                                                                error={null}
                                                            />
                                                        </td>
                                                        <td className="py-2 w-20">
                                                            <input type="number" min={1} value={a.dias}
                                                                onChange={(e) => actualizarAsignacion(viajero.id, rubro, 'dias', parseInt(e.target.value)||1)}
                                                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none" />
                                                        </td>
                                                        <td className="py-2 text-right font-medium">
                                                            {formatearMoneda(Number(a.valor_unitario) * Number(a.dias))}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        );
                    })}

                    <div className="flex justify-end gap-3">
                        <a href={route('solicitudes.show', solicitud.id)}
                            className="px-5 py-2 text-sm text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" disabled={processing}
                            className="px-5 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {processing ? 'Guardando…' : 'Guardar asignaciones'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
