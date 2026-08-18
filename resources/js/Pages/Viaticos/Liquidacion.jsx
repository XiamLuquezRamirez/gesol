import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import BadgeEstado from '@/Components/BadgeEstado';
import { formatearMoneda } from '@/lib/format';
import { useForm, Head } from '@inertiajs/react';
import { useState } from 'react';
import { PlusCircleIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { rubrosPorDefecto, diasComision } from '@/lib/rubros';

const etiquetaRubro = (r) =>
    r.charAt(0).toUpperCase() + r.slice(1).replace(/[_-]/g, ' ');

const formatFechaHora = (fecha, hora) => {
    if (!fecha) return '—';
    const f = new Date(String(fecha).substring(0, 10) + 'T00:00:00')
        .toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
    return hora ? `${f} · ${hora}` : f;
};

export default function Liquidacion({ solicitud, tarifas, rubros }) {
    const viajeros = solicitud.solicitable?.viajeros ?? [];

    const asignacionesIniciales = viajeros.flatMap((v) => {
        if (v.asignaciones?.length > 0) {
            return v.asignaciones.map((a) => ({
                viajero_comision_id: v.id,
                rubro:          a.rubro,
                valor_unitario: a.valor_unitario,
                dias:           a.dias,
            }));
        }
        // Rubros por defecto segun fechas Y horas: las horas afinan los bordes
        // (primer/ultimo dia) y la merienda es proporcional a las franjas del dia.
        return rubrosPorDefecto(v.fecha_salida, v.fecha_regreso, v.hora_salida, v.hora_regreso, rubros, tarifas)
            .map((a) => ({ viajero_comision_id: v.id, ...a }));
    });

    const pagosIniciales = viajeros.map((v) => ({
        viajero_comision_id: v.id,
        tipo_pago: v.tipo_pago ?? 'efectivo',
    }));

    const { data, setData, put, processing } = useForm({
        asignaciones: asignacionesIniciales,
        pagos: pagosIniciales,
    });

    const actualizarPago = (viajeroId, tipoPago) =>
        setData('pagos', data.pagos.map((p) =>
            p.viajero_comision_id === viajeroId ? { ...p, tipo_pago: tipoPago } : p
        ));

    /* ── selección del rubro a agregar por viajero ── */
    const [rubroSel, setRubroSel] = useState({});

    const rubrosUsados = (viajeroId) =>
        data.asignaciones.filter((a) => a.viajero_comision_id === viajeroId).map((a) => a.rubro);

    const rubrosDisponibles = (viajeroId) =>
        rubros.filter((r) => !rubrosUsados(viajeroId).includes(r));

    const agregarRubro = (viajeroId) => {
        const rubro = rubroSel[viajeroId];
        if (!rubro) return;
        const viajero = viajeros.find((v) => v.id === viajeroId);
        setData('asignaciones', [
            ...data.asignaciones,
            {
                viajero_comision_id: viajeroId,
                rubro,
                valor_unitario: tarifas[rubro]?.valor_sugerido ?? 0,
                dias: diasComision(viajero?.fecha_salida, viajero?.fecha_regreso),
            },
        ]);
        setRubroSel((p) => ({ ...p, [viajeroId]: '' }));
    };

    const quitarRubro = (viajeroId, rubro) =>
        setData('asignaciones', data.asignaciones.filter(
            (a) => !(a.viajero_comision_id === viajeroId && a.rubro === rubro)
        ));

    const actualizarAsignacion = (viajeroId, rubro, campo, valor) =>
        setData('asignaciones', data.asignaciones.map((a) =>
            a.viajero_comision_id === viajeroId && a.rubro === rubro ? { ...a, [campo]: valor } : a
        ));

    const submit = (e) => {
        e.preventDefault();
        put(route('viaticos.asignaciones', solicitud.id));
    };

    return (
        <AppLayout title={`Liquidación ${solicitud.radicado}`}>
            <Head title={`Liquidación ${solicitud.radicado}`} />
            <div className="p-6 w-full space-y-5">

                <div className="flex items-center gap-3">
                    <span className="font-mono text-base font-semibold text-slate-700">{solicitud.radicado}</span>
                    <BadgeEstado estado={solicitud.estado} />
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {viajeros.map((viajero) => {
                        const asigs    = data.asignaciones.filter((a) => a.viajero_comision_id === viajero.id);
                        const subtotal = asigs.reduce((acc, a) => acc + Number(a.valor_unitario) * Number(a.dias), 0);
                        const dispRubros = rubrosDisponibles(viajero.id);

                        return (
                            <div key={viajero.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden">

                                {/* ── Cabecera del viajero ── */}
                                <div className="flex items-start justify-between px-5 py-4 border-b border-slate-100">
                                    <div>
                                        <p className="text-sm font-semibold text-slate-800">
                                            {viajero.empleado
                                                ? `${viajero.empleado.nombres} ${viajero.empleado.apellidos}`
                                                : (viajero.nombre_externo || '—')}
                                        </p>
                                        <div className="flex items-center gap-3 mt-1 text-xs text-slate-500">
                                            <span>
                                                <span className="font-medium text-slate-400 mr-1">Salida</span>
                                                {formatFechaHora(viajero.fecha_salida, viajero.hora_salida)}
                                            </span>
                                            <span className="text-slate-300">→</span>
                                            <span>
                                                <span className="font-medium text-slate-400 mr-1">Regreso</span>
                                                {formatFechaHora(viajero.fecha_regreso, viajero.hora_regreso)}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Tipo de pago + subtotal */}
                                    <div className="flex flex-col items-end gap-2">
                                        <span className="text-sm font-semibold text-slate-800">
                                            {formatearMoneda(subtotal)}
                                        </span>
                                        <div className="flex items-center gap-1 text-xs">
                                            {[
                                                { valor: 'efectivo',     etiqueta: 'Efectivo' },
                                                { valor: 'transferencia', etiqueta: 'Transferencia' },
                                            ].map(({ valor, etiqueta }) => {
                                                const pagoActual = data.pagos.find((p) => p.viajero_comision_id === viajero.id)?.tipo_pago ?? 'efectivo';
                                                const activo = pagoActual === valor;
                                                return (
                                                    <button
                                                        key={valor}
                                                        type="button"
                                                        onClick={() => actualizarPago(viajero.id, valor)}
                                                        className={`px-2.5 py-1 rounded-full border font-medium transition-colors ${
                                                            activo
                                                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                                                : 'border-slate-300 text-slate-500 hover:border-indigo-400 hover:text-indigo-600'
                                                        }`}
                                                    >
                                                        {etiqueta}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>

                                {/* ── Tabla de rubros ── */}
                                <div className="overflow-x-auto">
                                    {asigs.length === 0 ? (
                                        <p className="text-xs text-slate-400 text-center py-6">
                                            Sin rubros seleccionados. Agrega uno abajo.
                                        </p>
                                    ) : (
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="text-left text-xs text-slate-500 border-b border-slate-100 bg-slate-50/50">
                                                    <th className="px-5 pb-2.5 pt-2.5 font-medium">Rubro</th>
                                                    <th className="px-5 pb-2.5 pt-2.5 font-medium w-48">Valor unitario</th>
                                                    <th className="px-5 pb-2.5 pt-2.5 font-medium w-24">Cantidad</th>
                                                    <th className="px-5 pb-2.5 pt-2.5 font-medium text-right w-36">Subtotal</th>
                                                    <th className="px-3 pb-2.5 pt-2.5 w-8"></th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-50">
                                                {asigs.map((a) => (
                                                    <tr key={a.rubro} className="hover:bg-slate-50/50">
                                                        <td className="px-5 py-2.5 text-slate-700 font-medium">
                                                            {etiquetaRubro(a.rubro)}
                                                        </td>
                                                        <td className="px-5 py-2.5">
                                                            <CampoMoneda
                                                                value={a.valor_unitario}
                                                                onChange={(v) => actualizarAsignacion(viajero.id, a.rubro, 'valor_unitario', v)}
                                                                error={null}
                                                            />
                                                        </td>
                                                        <td className="px-5 py-2.5">
                                                            <input
                                                                type="number" min={1} value={a.dias}
                                                                onChange={(e) => actualizarAsignacion(viajero.id, a.rubro, 'dias', parseInt(e.target.value) || 1)}
                                                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none"
                                                            />
                                                        </td>
                                                        <td className="px-5 py-2.5 text-right font-medium text-slate-800">
                                                            {formatearMoneda(Number(a.valor_unitario) * Number(a.dias))}
                                                        </td>
                                                        <td className="px-3 py-2.5">
                                                            <button
                                                                type="button"
                                                                onClick={() => quitarRubro(viajero.id, a.rubro)}
                                                                className="p-1 rounded text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors"
                                                                title="Quitar rubro"
                                                            >
                                                                <XMarkIcon className="w-4 h-4" />
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </div>

                                {/* ── Agregar rubro ── */}
                                {dispRubros.length > 0 && (
                                    <div className="flex items-center gap-2 px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                                        <select
                                            value={rubroSel[viajero.id] ?? ''}
                                            onChange={(e) => setRubroSel((p) => ({ ...p, [viajero.id]: e.target.value }))}
                                            className="flex-1 rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none bg-white"
                                        >
                                            <option value="">— Agregar rubro —</option>
                                            {dispRubros.map((r) => (
                                                <option key={r} value={r}>{etiquetaRubro(r)}</option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            onClick={() => agregarRubro(viajero.id)}
                                            disabled={!rubroSel[viajero.id]}
                                            className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 rounded-lg transition-colors"
                                        >
                                            <PlusCircleIcon className="w-4 h-4" /> Agregar
                                        </button>
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    {/* ── Resumen de totales ── */}
                    {(() => {
                        const totalEfectivo = viajeros.reduce((acc, v) => {
                            const tipoPago = data.pagos.find((p) => p.viajero_comision_id === v.id)?.tipo_pago ?? 'efectivo';
                            if (tipoPago !== 'efectivo') return acc;
                            return acc + data.asignaciones
                                .filter((a) => a.viajero_comision_id === v.id)
                                .reduce((s, a) => s + Number(a.valor_unitario) * Number(a.dias), 0);
                        }, 0);
                        const totalTransferencia = viajeros.reduce((acc, v) => {
                            const tipoPago = data.pagos.find((p) => p.viajero_comision_id === v.id)?.tipo_pago ?? 'efectivo';
                            if (tipoPago !== 'transferencia') return acc;
                            return acc + data.asignaciones
                                .filter((a) => a.viajero_comision_id === v.id)
                                .reduce((s, a) => s + Number(a.valor_unitario) * Number(a.dias), 0);
                        }, 0);
                        const totalGeneral = totalEfectivo + totalTransferencia;

                        return (
                            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                                <div className="px-5 py-3 border-b border-slate-100 bg-slate-50/50">
                                    <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Resumen de pagos</p>
                                </div>
                                <div className="divide-y divide-slate-100">
                                    {totalEfectivo > 0 && (
                                        <div className="flex items-center justify-between px-5 py-3">
                                            <span className="text-sm text-slate-600">Total en efectivo</span>
                                            <span className="text-sm font-medium text-slate-800">{formatearMoneda(totalEfectivo)}</span>
                                        </div>
                                    )}
                                    {totalTransferencia > 0 && (
                                        <div className="flex items-center justify-between px-5 py-3">
                                            <span className="text-sm text-slate-600">Total en transferencia</span>
                                            <span className="text-sm font-medium text-slate-800">{formatearMoneda(totalTransferencia)}</span>
                                        </div>
                                    )}
                                    <div className="flex items-center justify-between px-5 py-3.5">
                                        <span className="text-sm font-semibold text-slate-800">Total general</span>
                                        <span className="text-base font-bold text-indigo-700">{formatearMoneda(totalGeneral)}</span>
                                    </div>
                                </div>
                            </div>
                        );
                    })()}

                    {/* ── Botones ── */}
                    <div className="flex justify-end gap-3 pt-1">
                        <a href={route('solicitudes.show', solicitud.id)}
                            className="px-5 py-2 text-sm text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                            Cancelar
                        </a>
                        <button type="submit" disabled={processing}
                            className="px-5 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {processing
                                ? 'Guardando…'
                                : solicitud.estado === 'liquidada'
                                    ? 'Guardar cambios'
                                    : 'Guardar informe'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
