import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import { formatearMoneda } from '@/lib/format';
import { useForm, Head } from '@inertiajs/react';

const etiquetaRubro = (r) =>
    r.charAt(0).toUpperCase() + r.slice(1).replace(/[_-]/g, ' ');

/**
 * Pantalla de liquidacion de un ajuste-anexo (comision cerrada). El contador
 * revisa el delta de rubros propuesto (calculado por el backend a partir de las
 * fechas o del rubro/cantidad), ajusta el valor unitario/dias y confirma. Al
 * guardar, el ajuste queda liquidado y pasa al lider de contabilidad para aprobar.
 */
export default function LiquidacionAjuste({ solicitud, ajuste, delta = [], tarifas = {}, rubros = [] }) {
    const { data, setData, put, processing } = useForm({
        asignaciones: delta.map((fila) => ({
            rubro: fila.rubro,
            valor_unitario: Number(fila.valor_unitario) || 0,
            dias: Number(fila.dias) || 0,
        })),
    });

    const actualizar = (indice, campo, valor) => {
        setData(
            'asignaciones',
            data.asignaciones.map((fila, i) =>
                i === indice ? { ...fila, [campo]: valor } : fila
            )
        );
    };

    const total = data.asignaciones.reduce(
        (acc, fila) => acc + (Number(fila.valor_unitario) || 0) * (Number(fila.dias) || 0),
        0
    );

    const enviar = (e) => {
        e.preventDefault();
        put(route('viaticos.ajuste.asignaciones', [solicitud.id, ajuste.id]));
    };

    return (
        <AppLayout>
            <Head title={`Liquidar ajuste ${solicitud.radicado}`} />
            <div className="mx-auto max-w-3xl px-4 py-8">
                <h1 className="text-xl font-semibold text-gray-800">
                    Liquidar ajuste — {solicitud.radicado}
                </h1>
                <p className="mt-1 text-sm text-gray-500">{ajuste.motivo}</p>

                <form onSubmit={enviar} className="mt-6 space-y-4">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-gray-500">
                                <th className="py-2">Rubro</th>
                                <th className="py-2">Valor unitario</th>
                                <th className="py-2">Dias</th>
                                <th className="py-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.asignaciones.map((fila, i) => (
                                <tr key={i} className="border-t">
                                    <td className="py-2">{etiquetaRubro(fila.rubro)}</td>
                                    <td className="py-2">
                                        <CampoMoneda
                                            value={fila.valor_unitario}
                                            onChange={(v) => actualizar(i, 'valor_unitario', v)}
                                        />
                                    </td>
                                    <td className="py-2">
                                        <input
                                            type="number"
                                            className="w-20 rounded border-gray-300 text-sm"
                                            value={fila.dias}
                                            onChange={(e) => actualizar(i, 'dias', Number(e.target.value))}
                                        />
                                    </td>
                                    <td className="py-2">
                                        {formatearMoneda(
                                            (Number(fila.valor_unitario) || 0) * (Number(fila.dias) || 0)
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    <div className="text-right font-semibold text-gray-800">
                        Total del ajuste: {formatearMoneda(total)}
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            Liquidar ajuste
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
