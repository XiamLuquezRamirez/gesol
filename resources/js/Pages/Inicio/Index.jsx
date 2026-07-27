import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import { Head, Link, usePage } from '@inertiajs/react';

function getGreeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Buenos días';
    if (h < 18) return 'Buenas tardes';
    return 'Buenas noches';
}

const STAT_ICONS = {
    indigo: {
        wrap: 'bg-indigo-50 ring-indigo-100 text-indigo-500',
        path: 'M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Zm0 4.875c-1.035 0-1.875.84-1.875 1.875V17.25c0 1.035.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875V9.75c0-1.035-.84-1.875-1.875-1.875H3.375Z',
    },
    amber: {
        wrap: 'bg-amber-50 ring-amber-100 text-amber-500',
        path: 'M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z',
    },
    green: {
        wrap: 'bg-emerald-50 ring-emerald-100 text-emerald-500',
        path: 'M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z',
    },
};

function StatCard({ label, value, sublabel, color }) {
    const s = STAT_ICONS[color] ?? STAT_ICONS.indigo;
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-4 hover:shadow-sm transition-shadow duration-200">
            <div className={`w-10 h-10 rounded-lg ring-1 flex items-center justify-center shrink-0 ${s.wrap}`}>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                    <path fillRule="evenodd" d={s.path} clipRule="evenodd" />
                </svg>
            </div>
            <div>
                <p className="text-xl font-bold text-slate-800 leading-tight">{value}</p>
                <p className="text-sm font-medium text-slate-700">{label}</p>
                <p className="text-xs text-slate-400 mt-0.5">{sublabel}</p>
            </div>
        </div>
    );
}

const ACCESOS = [
    {
        key: 'oficina',
        titulo: 'Nueva solicitud de oficina',
        descripcion: 'Solicitar materiales, suministros y elementos de oficina.',
        color: 'indigo',
        ruta: () => route('oficina.crear'),
        icono: (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                <path fillRule="evenodd" d="M3 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5H15v-18a.75.75 0 0 0 0-1.5H3ZM6.75 19.5v-2.25a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75ZM6 6.75A.75.75 0 0 1 6.75 6h.75a.75.75 0 0 1 0 1.5h-.75A.75.75 0 0 1 6 6.75ZM6.75 9a.75.75 0 0 0 0 1.5h.75a.75.75 0 0 0 0-1.5h-.75ZM6 12.75a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM10.5 6a.75.75 0 0 0 0 1.5h.75a.75.75 0 0 0 0-1.5h-.75Zm-.75 3.75A.75.75 0 0 1 10.5 9h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM10.5 12a.75.75 0 0 0 0 1.5h.75a.75.75 0 0 0 0-1.5h-.75ZM16.5 6.75v15h5.25a.75.75 0 0 0 0-1.5H21v-12a.75.75 0 0 0 0-1.5h-4.5Zm1.5 4.5a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 0 1.5H18.75A.75.75 0 0 1 18 11.25Zm.75 2.25a.75.75 0 0 0 0 1.5h.008a.75.75 0 0 0 0-1.5H18.75Zm-.75 4.5a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 0 1.5H18.75a.75.75 0 0 1-.75-.75Z" clipRule="evenodd" />
            </svg>
        ),
    },
    {
        key: 'viaticos',
        titulo: 'Nueva solicitud de viáticos',
        descripcion: 'Registrar una comisión, desplazamiento o viaje oficial.',
        color: 'emerald',
        ruta: () => route('viaticos.crear'),
        icono: (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                <path fillRule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-2.003 3.5-4.697 3.5-8.327a8 8 0 1 0-16 0c0 3.63 1.556 6.326 3.5 8.327a19.583 19.583 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.144.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clipRule="evenodd" />
            </svg>
        ),
    },
    {
        key: 'mis-solicitudes',
        titulo: 'Mis solicitudes',
        descripcion: 'Consultar el estado de tus solicitudes activas e historial.',
        color: 'slate',
        ruta: () => route('solicitudes.index'),
        icono: (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                <path fillRule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z" clipRule="evenodd" />
                <path fillRule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375Zm9.586 4.594a.75.75 0 0 0-1.172-.938l-2.476 3.096-.908-.907a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.116-.062l3-3.75Z" clipRule="evenodd" />
            </svg>
        ),
    },
    {
        key: 'pendientes',
        titulo: 'Pendientes por aprobar',
        descripcion: 'Solicitudes que esperan tu acción según tu rol de aprobación.',
        color: 'amber',
        ruta: () => route('solicitudes.index', { tab: 'pendientes' }),
        icono: (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                <path fillRule="evenodd" d="M12 2.25A6.75 6.75 0 0 0 5.25 9v.75a8.217 8.217 0 0 1-2.119 5.52.75.75 0 0 0 .298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 1 0 7.48 0 24.583 24.583 0 0 0 4.83-1.244.75.75 0 0 0 .298-1.205 8.217 8.217 0 0 1-2.118-5.52V9A6.75 6.75 0 0 0 12 2.25ZM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 0 0 4.496 0l.002.1a2.25 2.25 0 1 1-4.5 0Z" clipRule="evenodd" />
            </svg>
        ),
    },
];

const COLOR_ACCESO = {
    indigo:  { bg: 'bg-indigo-50',  icon: 'bg-indigo-100 text-indigo-600',  arrow: 'text-indigo-400',  hover: 'hover:border-indigo-200 hover:bg-indigo-50/50' },
    emerald: { bg: 'bg-emerald-50', icon: 'bg-emerald-100 text-emerald-600', arrow: 'text-emerald-400', hover: 'hover:border-emerald-200 hover:bg-emerald-50/50' },
    slate:   { bg: 'bg-slate-50',   icon: 'bg-slate-100 text-slate-600',    arrow: 'text-slate-400',   hover: 'hover:border-slate-300 hover:bg-slate-50' },
    amber:   { bg: 'bg-amber-50',   icon: 'bg-amber-100 text-amber-600',    arrow: 'text-amber-400',   hover: 'hover:border-amber-200 hover:bg-amber-50/50' },
};

function AccesoCard({ titulo, descripcion, color, ruta, icono, badge }) {
    const c = COLOR_ACCESO[color] ?? COLOR_ACCESO.slate;
    return (
        <Link
            href={ruta()}
            className={`group bg-white rounded-xl border border-slate-200 p-5 flex items-start gap-4 transition-all duration-150 ${c.hover}`}
        >
            <div className={`shrink-0 w-10 h-10 rounded-lg flex items-center justify-center ${c.icon}`}>
                {icono}
            </div>
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                    <h3 className="text-sm font-semibold text-slate-800 group-hover:text-slate-900">{titulo}</h3>
                    {badge != null && badge > 0 && (
                        <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-[10px] font-bold">
                            {badge > 9 ? '9+' : badge}
                        </span>
                    )}
                </div>
                <p className="text-xs text-slate-500 leading-relaxed">{descripcion}</p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                className={`w-4 h-4 shrink-0 mt-0.5 transition-transform duration-150 group-hover:translate-x-0.5 ${c.arrow}`}>
                <path fillRule="evenodd" d="M16.28 11.47a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 0 1-1.06-1.06L14.69 12 7.72 5.03a.75.75 0 0 1 1.06-1.06l7.5 7.5Z" clipRule="evenodd" />
            </svg>
        </Link>
    );
}

export default function Index({ stats, recientes }) {
    const { auth } = usePage().props;
    const usuario = auth.user;
    const roles = usuario.roles?.map((r) => r.name) ?? [];
    const primerNombre = usuario.name.split(' ')[0];

    const fecha = new Date().toLocaleDateString('es-CO', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });

    const badgePorAcceso = {
        'pendientes': stats?.pendientes ?? 0,
    };

    return (
        <AppLayout title="Inicio">
            <Head title="Inicio" />

            <div className="flex-1 flex flex-col px-6 py-8 gap-6">

                {/* Saludo */}
                <div>
                    <p className="text-sm text-slate-400 capitalize mb-1">{fecha}</p>
                    <h2 className="text-2xl font-bold text-slate-900 mb-3">
                        {getGreeting()}, {primerNombre}
                    </h2>
                    {roles.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {roles.map((r) => (
                                <span key={r}
                                    className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {r.replace(/_/g, ' ')}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {/* Estadísticas */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <StatCard label="Mis solicitudes" value={stats?.mis_solicitudes ?? 0}
                        sublabel={stats?.mis_solicitudes ? 'Solicitudes activas' : 'Sin solicitudes activas'}
                        color="indigo" />
                    <StatCard label="Pendientes" value={stats?.pendientes ?? 0}
                        sublabel={stats?.pendientes ? 'Esperan tu acción' : 'Nada que aprobar hoy'}
                        color="amber" />
                    <StatCard label="Completadas" value={stats?.completadas ?? 0}
                        sublabel="Este mes" color="green" />
                </div>

                {/* Accesos rápidos */}
                <div>
                    <p className="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">
                        Accesos rápidos
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {ACCESOS.map((a) => (
                            <AccesoCard
                                key={a.key}
                                titulo={a.titulo}
                                descripcion={a.descripcion}
                                color={a.color}
                                ruta={a.ruta}
                                icono={a.icono}
                                badge={badgePorAcceso[a.key]}
                            />
                        ))}
                    </div>
                </div>

                {/* Solicitudes recientes */}
                {recientes?.length > 0 && (
                    <div>
                        <div className="flex items-center justify-between mb-3">
                            <p className="text-xs font-semibold text-slate-400 uppercase tracking-widest">
                                Mis solicitudes recientes
                            </p>
                            <Link href={route('solicitudes.index')}
                                className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                Ver todas →
                            </Link>
                        </div>
                        <div className="bg-white rounded-xl border border-slate-200 divide-y divide-slate-50">
                            {recientes.map((s) => (
                                <Link
                                    key={s.id}
                                    href={route('solicitudes.show', s.id)}
                                    className="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 transition-colors group"
                                >
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-mono font-semibold text-slate-700 group-hover:text-indigo-700 transition-colors">
                                            {s.radicado}
                                        </p>
                                        <p className="text-xs text-slate-400 truncate">{s.tipo} · {s.fecha}</p>
                                    </div>
                                    <BadgeEstado estado={s.estado} />
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

            </div>
        </AppLayout>
    );
}
