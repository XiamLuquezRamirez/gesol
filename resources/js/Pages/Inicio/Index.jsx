import AppLayout from '@/Layouts/AppLayout';
import { Head, usePage } from '@inertiajs/react';

function getGreeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Buenos días';
    if (h < 18) return 'Buenas tardes';
    return 'Buenas noches';
}

function StatCard({ label, value, sublabel, iconPath, color }) {
    const styles = {
        indigo: { wrap: 'bg-indigo-50 ring-indigo-100', icon: 'text-indigo-500' },
        amber:  { wrap: 'bg-amber-50 ring-amber-100',   icon: 'text-amber-500'  },
        green:  { wrap: 'bg-emerald-50 ring-emerald-100', icon: 'text-emerald-500' },
    };
    const s = styles[color] ?? styles.indigo;

    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-4 hover:shadow-sm transition-shadow duration-200">
            <div className={`w-10 h-10 rounded-lg ${s.wrap} ring-1 flex items-center justify-center shrink-0`}>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={`w-5 h-5 ${s.icon}`}>
                    <path fillRule="evenodd" d={iconPath} clipRule="evenodd" />
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

function QuickCard({ title, description, badge, children }) {
    return (
        <div className="bg-white rounded-xl border border-slate-200 p-6 flex flex-col hover:border-slate-300 hover:shadow-sm transition-all duration-200">
            <div className="flex items-start justify-between mb-4">
                <div className="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 shrink-0">
                    {children}
                </div>
                {badge && (
                    <span className="text-xs text-slate-400 bg-slate-100 px-2.5 py-0.5 rounded-full font-medium">
                        {badge}
                    </span>
                )}
            </div>
            <h3 className="font-semibold text-slate-900 text-sm mb-1.5">{title}</h3>
            <p className="text-sm text-slate-500 leading-relaxed flex-1">{description}</p>
        </div>
    );
}

export default function Index() {
    const { auth } = usePage().props;
    const usuario = auth.user;
    const roles = usuario.roles?.map((r) => r.name) ?? [];
    const primerNombre = usuario.name.split(' ')[0];

    const fecha = new Date().toLocaleDateString('es-CO', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <AppLayout title="Inicio">
            <Head title="Inicio" />

            <div className="flex-1 flex flex-col px-6 py-8 gap-6">

                {/* Greeting */}
                <div>
                    <p className="text-sm text-slate-400 capitalize mb-1">{fecha}</p>
                    <h2 className="text-2xl font-bold text-slate-900 mb-3">
                        {getGreeting()}, {primerNombre}
                    </h2>
                    {roles.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {roles.map((r) => (
                                <span
                                    key={r}
                                    className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100"
                                >
                                    {r.replace(/_/g, ' ')}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <StatCard
                        label="Mis solicitudes"
                        value="0"
                        sublabel="Sin solicitudes activas"
                        color="indigo"
                        iconPath="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Zm0 4.875c-1.035 0-1.875.84-1.875 1.875V17.25c0 1.035.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875V9.75c0-1.035-.84-1.875-1.875-1.875H3.375Z"
                    />
                    <StatCard
                        label="Pendientes"
                        value="0"
                        sublabel="Nada que aprobar hoy"
                        color="amber"
                        iconPath="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z"
                    />
                    <StatCard
                        label="Completadas"
                        value="0"
                        sublabel="Este mes"
                        color="green"
                        iconPath="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
                    />
                </div>

                {/* Quick access — fills remaining space */}
                <div className="flex-1 flex flex-col min-h-0">
                    <p className="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-4">
                        Accesos rápidos
                    </p>
                    <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <QuickCard
                            title="Mis solicitudes"
                            description="Revisa el estado de tus solicitudes de oficina y viáticos en curso."
                            badge="Próximamente"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                                <path fillRule="evenodd" d="M7.502 6h7.128A3.375 3.375 0 0 1 18 9.375v9.375a3 3 0 0 0 3-3V6.108c0-1.505-1.125-2.811-2.664-2.94a48.972 48.972 0 0 0-.673-.05A3 3 0 0 0 15 1.5h-1.5a3 3 0 0 0-2.663 1.618c-.225.015-.45.032-.673.05C8.662 3.295 7.554 4.542 7.502 6ZM13.5 3A1.5 1.5 0 0 0 12 4.5h4.5A1.5 1.5 0 0 0 15 3h-1.5Z" clipRule="evenodd" />
                                <path fillRule="evenodd" d="M3 9.375C3 8.339 3.84 7.5 4.875 7.5h9.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-9.75A1.875 1.875 0 0 1 3 20.625V9.375Zm9.586 4.594a.75.75 0 0 0-1.172-.938l-2.476 3.096-.908-.907a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.116-.062l3-3.75Z" clipRule="evenodd" />
                            </svg>
                        </QuickCard>

                        <QuickCard
                            title="Pendientes por aprobar"
                            description="Solicitudes que esperan tu acción según tu rol en el flujo de aprobación."
                            badge="Próximamente"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
                                <path d="M5.85 3.5a.75.75 0 0 0-1.117-1 9.719 9.719 0 0 0-2.348 4.876.75.75 0 0 0 1.479.248A8.219 8.219 0 0 1 5.85 3.5ZM19.267 2.5a.75.75 0 1 0-1.118 1 8.22 8.22 0 0 1 1.987 4.124.75.75 0 0 0 1.48-.248A9.72 9.72 0 0 0 19.266 2.5Z" />
                                <path fillRule="evenodd" d="M12 2.25A6.75 6.75 0 0 0 5.25 9v.75a8.217 8.217 0 0 1-2.119 5.52.75.75 0 0 0 .298 1.206c1.544.57 3.16.99 4.831 1.243a3.75 3.75 0 1 0 7.48 0 24.583 24.583 0 0 0 4.83-1.244.75.75 0 0 0 .298-1.205 8.217 8.217 0 0 1-2.118-5.52V9A6.75 6.75 0 0 0 12 2.25ZM9.75 18c0-.034 0-.067.002-.1a25.05 25.05 0 0 0 4.496 0l.002.1a2.25 2.25 0 1 1-4.5 0Z" clipRule="evenodd" />
                            </svg>
                        </QuickCard>
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}
