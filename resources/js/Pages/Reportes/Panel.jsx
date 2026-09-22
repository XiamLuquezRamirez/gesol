import AppLayout from '@/Layouts/AppLayout';
import { formatearMoneda } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import FiltroRango from './Componentes/FiltroRango';

/**
 * Panel de reportes: cada informe es una tarjeta con un indicador rapido del rango
 * seleccionado. Al hacer clic se entra a la vista dedicada de ese informe, llevando
 * el mismo rango de fechas.
 */
const TARJETAS = [
    {
        key: 'viaticos', ruta: 'reportes.viaticos', titulo: 'Viáticos liquidados',
        desc: 'Comisiones, rubros y comprobantes de pago por empleado.',
        color: 'bg-indigo-50 text-indigo-700 border-indigo-100',
        valor: (r) => formatearMoneda(r.viaticos),
    },
    {
        key: 'por_viajero', ruta: 'reportes.por-viajero', titulo: 'Gasto por viajero',
        desc: 'Total de comisiones por empleado, con gasto y comprobantes por comisión.',
        color: 'bg-violet-50 text-violet-700 border-violet-100',
        valor: (r) => formatearMoneda(r.viaticos),
    },
    {
        key: 'oficina', ruta: 'reportes.oficina', titulo: 'Oficina: aprobado vs. pagado',
        desc: 'Pagos de solicitudes de oficina y saldos pendientes.',
        color: 'bg-emerald-50 text-emerald-700 border-emerald-100',
        valor: (r) => formatearMoneda(r.oficina),
    },
    {
        key: 'personal', ruta: 'reportes.personal', titulo: 'Personal en comisión',
        desc: 'Empleados por fuera y días de comisión.',
        color: 'bg-sky-50 text-sky-700 border-sky-100',
        valor: (r) => `${r.personal} empleados`,
    },
    {
        key: 'pendientes', ruta: 'reportes.comprobantes', titulo: 'Comprobantes pendientes',
        desc: 'Comisiones cerradas sin comprobante de transferencia.',
        color: 'bg-amber-50 text-amber-700 border-amber-100',
        valor: (r) => `${r.pendientes} comisiones`,
    },
    {
        key: 'reajustes', ruta: 'reportes.reajustes', titulo: 'Reajustes',
        desc: 'Ajustes post-cierre y su valor delta.',
        color: 'bg-rose-50 text-rose-700 border-rose-100',
        valor: (r) => `${r.reajustes} ajustes`,
    },
];

export default function Panel({ filtros, resumen }) {
    const [desde, setDesde] = useState(filtros?.desde ?? '');
    const [hasta, setHasta] = useState(filtros?.hasta ?? '');

    useEffect(() => {
        setDesde(filtros?.desde ?? '');
        setHasta(filtros?.hasta ?? '');
    }, [filtros]);

    const consultar = (d, h) => {
        router.get(route('reportes.index'),
            { desde: d || undefined, hasta: h || undefined },
            { preserveState: true, replace: true });
    };

    const query = { desde: filtros?.desde, hasta: filtros?.hasta };

    return (
        <AppLayout title="Reportes">
            <Head title="Reportes" />
            <div className="p-6 w-full space-y-5">
                <div>
                    <h2 className="text-lg font-semibold text-slate-800">Reportes</h2>
                    <p className="text-sm text-slate-500 mt-0.5">
                        Elige un informe. El rango de fechas seleccionado se aplica a cada uno.
                    </p>
                </div>

                <FiltroRango
                    desde={desde} hasta={hasta}
                    setDesde={setDesde} setHasta={setHasta}
                    onConsultar={consultar}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {TARJETAS.map((t) => (
                        <Link
                            key={t.key}
                            href={route(t.ruta, query)}
                            className="group rounded-xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:border-slate-200"
                        >
                            <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${t.color}`}>
                                {t.valor(resumen)}
                            </span>
                            <h3 className="mt-3 text-sm font-semibold text-slate-800 group-hover:text-indigo-700">
                                {t.titulo}
                            </h3>
                            <p className="mt-1 text-xs text-slate-500">{t.desc}</p>
                            <span className="mt-3 inline-block text-xs font-medium text-indigo-600">Ver informe →</span>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
