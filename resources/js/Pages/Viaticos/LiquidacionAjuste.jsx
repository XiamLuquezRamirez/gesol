import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import BadgeEstado from '@/Components/BadgeEstado';
import { formatearMoneda } from '@/lib/format';
import { useForm, Head, Link } from '@inertiajs/react';
import { XMarkIcon } from '@heroicons/react/24/outline';

const etiquetaRubro = (r) =>
    r.charAt(0).toUpperCase() + r.slice(1).replace(/[_-]/g, ' ');

/** Nombre a mostrar del viajero del ajuste (accesor, empleado o externo). */
const nombreViajero = (ajuste) => {
    if (ajuste?.viajero?.nombre_mostrado) return ajuste.viajero.nombre_mostrado;
    const emp = ajuste?.viajero?.empleado;
    if (emp) return `${emp.nombres} ${emp.apellidos}`;
    return ajuste?.viajero?.nombre_externo || '—';
};

/**
 * Pantalla de liquidacion de un ajuste-anexo (comision cerrada). El contador
 * revisa el delta de rubros propuesto (calculado por el backend a partir de las
 * fechas o del rubro/cantidad), ajusta el valor unitario/dias y confirma. Al
 * guardar, el ajuste queda liquidado y pasa al lider de contabilidad para aprobar.
 * No altera la liquidacion original: la comision sigue cerrada.
 */
export default function LiquidacionAjuste({ solicitud, ajuste, delta = [], tarifas = {}, rubros = [], puedeLiquidar = true }) {
    const { data, setData, put, processing } = useForm({
        asignaciones: (delta ?? []).map((fila) => ({
            rubro: fila.rubro,
            valor_unitario: Number(fila.valor_unitario) || 0,
            dias: Number.isFinite(Number(fila.dias)) ? Number(fila.dias) : 0,
        })),
    });

    const actualizar = (indice, campo, valor) =>
        setData(
            'asignaciones',
            data.asignaciones.map((fila, i) =>
                i === indice ? { ...fila, [campo]: valor } : fila
            )
        );

    const quitarRubro = (indice) =>
        setData('asignaciones', data.asignaciones.filter((_, i) => i !== indice));

    const subtotalDe = (fila) => (Number(fila.valor_unitario) || 0) * (Number(fila.dias) || 0);
    const total = data.asignaciones.reduce((acc, fila) => acc + subtotalDe(fila), 0);

    const enviar = (e) => {
        e.preventDefault();
        put(route('viaticos.ajuste.asignaciones', [solicitud.id, ajuste.id]));
    };

    return (
        <AppLayout title={`Liquidar ajuste ${solicitud.radicado}`}>
            <Head title={`Liquidar ajuste ${solicitud.radicado}`} />
            <div className="p-6 w-full max-w-3xl mx-auto space-y-5">

                <div className="flex items-center gap-3">
                    <span className="font-mono text-base font-semibold text-slate-700">{solicitud.radicado}</span>
                    <BadgeEstado estado={solicitud.estado} />
                </div>

                {/* ── Aviso de anexo ── */}
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Este es un ajuste (anexo) sobre la comisión{' '}
                    <span className="font-semibold">{solicitud.radicado}</span>, ya cerrada.
                    No modifica la liquidación original.
                </div>

                {/* ── Devolucion del lider de contabilidad ── */}
                {ajuste.motivo_devolucion && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <span className="font-semibold">Devuelto por el líder de contabilidad:</span>{' '}
                        {ajuste.motivo_devolucion}
                    </div>
                )}

                <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    {/* ── Cabecera del ajuste ── */}
                    <div className="px-5 py-4 border-b border-slate-100">
                        <p className="text-sm font-semibold text-slate-800">{nombreViajero(ajuste)}</p>
                        {ajuste.motivo && (
                            <p className="mt-1 text-sm text-slate-500">
                                <span className="font-medium text-slate-400 mr-1">Motivo</span>
                                {ajuste.motivo}
                            </p>
                        )}
                    </div>

                    <form onSubmit={enviar}>
                        {/* ── Tabla del delta ── */}
                        <div className="overflow-x-auto">
                            {data.asignaciones.length === 0 ? (
                                <p className="text-xs text-slate-400 text-center py-6">
                                    Sin rubros en el ajuste.
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-xs text-slate-500 border-b border-slate-100 bg-slate-50/50">
                                            <th className="px-5 pb-2.5 pt-2.5 font-medium">Rubro</th>
                                            <th className="px-5 pb-2.5 pt-2.5 font-medium w-28">Días (±)</th>
                                            <th className="px-5 pb-2.5 pt-2.5 font-medium w-48">Valor unitario</th>
                                            <th className="px-5 pb-2.5 pt-2.5 font-medium text-right w-36">Subtotal</th>
                                            <th className="px-3 pb-2.5 pt-2.5 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {data.asignaciones.map((fila, i) => {
                                            const sub = subtotalDe(fila);
                                            return (
                                                <tr key={i} className="hover:bg-slate-50/50">
                                                    <td className="px-5 py-2.5 text-slate-700 font-medium">
                                                        {etiquetaRubro(fila.rubro)}
                                                    </td>
                                                    <td className="px-5 py-2.5">
                                                        <input
                                                            type="number"
                                                            value={fila.dias}
                                                            readOnly={!puedeLiquidar}
                                                            onChange={(e) =>
                                                                actualizar(i, 'dias', parseInt(e.target.value, 10) || 0)
                                                            }
                                                            className={[
                                                                'w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none',
                                                                !puedeLiquidar ? 'bg-slate-50 text-slate-500' : '',
                                                                fila.dias < 0 ? 'text-red-600 font-semibold' : '',
                                                            ].join(' ')}
                                                        />
                                                    </td>
                                                    <td className="px-5 py-2.5">
                                                        {puedeLiquidar ? (
                                                            <CampoMoneda
                                                                value={fila.valor_unitario}
                                                                onChange={(v) => actualizar(i, 'valor_unitario', v)}
                                                                error={null}
                                                            />
                                                        ) : (
                                                            <span className="text-slate-700">{formatearMoneda(fila.valor_unitario)}</span>
                                                        )}
                                                    </td>
                                                    <td className={[
                                                        'px-5 py-2.5 text-right font-medium',
                                                        sub < 0 ? 'text-red-600' : 'text-slate-800',
                                                    ].join(' ')}>
                                                        {formatearMoneda(sub)}
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        {puedeLiquidar && (
                                                            <button
                                                                type="button"
                                                                onClick={() => quitarRubro(i)}
                                                                className="p-1 rounded text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors"
                                                                title="Quitar rubro"
                                                            >
                                                                <XMarkIcon className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            )}
                        </div>

                        {/* ── Total del ajuste ── */}
                        <div className="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50/50">
                            <span className="text-sm font-semibold text-slate-800">Total del ajuste</span>
                            <span className={[
                                'text-base font-bold',
                                total < 0 ? 'text-red-600' : 'text-indigo-700',
                            ].join(' ')}>
                                {formatearMoneda(total)}
                            </span>
                        </div>

                        {/* ── Botones ── */}
                        <div className="flex justify-end gap-3 px-5 py-4 border-t border-slate-100">
                            <Link
                                href={route('solicitudes.show', solicitud.id)}
                                className="px-5 py-2 text-sm text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
                            >
                                {puedeLiquidar ? 'Cancelar' : 'Volver'}
                            </Link>
                            {puedeLiquidar && (
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {processing ? 'Guardando…' : 'Guardar liquidación del ajuste'}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
