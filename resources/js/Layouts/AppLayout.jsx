import { router, Link, usePage } from '@inertiajs/react';
import PanelNotificaciones from '@/Components/PanelNotificaciones';
import CargandoOverlay from '@/Components/CargandoOverlay';

const IconInbox = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M6.912 3a3 3 0 0 0-2.868 2.118l-2.411 7.838a3 3 0 0 0-.133.882V18a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-4.162c0-.299-.045-.596-.133-.882l-2.412-7.838A3 3 0 0 0 17.088 3H6.912Zm13.823 9.75-2.213-7.191A1.5 1.5 0 0 0 17.088 4.5H6.912a1.5 1.5 0 0 0-1.434 1.059L3.265 12.75H6.11a3 3 0 0 1 2.684 1.658l.256.513a1.5 1.5 0 0 0 1.342.829h3.218a1.5 1.5 0 0 0 1.342-.83l.256-.512a3 3 0 0 1 2.684-1.658h2.844Z" clipRule="evenodd" />
    </svg>
);

const IconBuilding = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M3 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5H15v-18a.75.75 0 0 0 0-1.5H3ZM6.75 19.5v-2.25a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-.75.75h-3a.75.75 0 0 1-.75-.75ZM6 6.75A.75.75 0 0 1 6.75 6h.75a.75.75 0 0 1 0 1.5h-.75A.75.75 0 0 1 6 6.75ZM6.75 9a.75.75 0 0 0 0 1.5h.75a.75.75 0 0 0 0-1.5h-.75ZM6 12.75a.75.75 0 0 1 .75-.75h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM10.5 6a.75.75 0 0 0 0 1.5h.75a.75.75 0 0 0 0-1.5h-.75Zm-.75 3.75A.75.75 0 0 1 10.5 9h.75a.75.75 0 0 1 0 1.5h-.75a.75.75 0 0 1-.75-.75ZM10.5 12a.75.75 0 0 0 0 1.5h.75a.75.75 0 0 0 0-1.5h-.75ZM16.5 6.75v15h5.25a.75.75 0 0 0 0-1.5H21v-12a.75.75 0 0 0 0-1.5h-4.5Zm1.5 4.5a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 0 1.5H18.75A.75.75 0 0 1 18 11.25Zm.75 2.25a.75.75 0 0 0 0 1.5h.008a.75.75 0 0 0 0-1.5H18.75Zm-.75 4.5a.75.75 0 0 1 .75-.75h.008a.75.75 0 0 1 0 1.5H18.75a.75.75 0 0 1-.75-.75Z" clipRule="evenodd" />
    </svg>
);

const IconMap = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-2.003 3.5-4.697 3.5-8.327a8 8 0 1 0-16 0c0 3.63 1.556 6.326 3.5 8.327a19.583 19.583 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.144.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clipRule="evenodd" />
    </svg>
);

const IconHome = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path d="M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z" />
        <path d="m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.43Z" />
    </svg>
);

const IconCog = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.889c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 0 0 0 1.139c.015.2-.059.352-.153.43l-.841.692a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.985.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.344-.165.673-.356.985-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 0 0 0-1.139c-.016-.2.059-.352.153-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.49 7.49 0 0 0-.985-.57c-.183-.087-.277-.227-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843ZM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z" clipRule="evenodd" />
    </svg>
);

const IconUser = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clipRule="evenodd" />
    </svg>
);

const IconUsers = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
    </svg>
);

const IconLogout = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M16.5 3.75a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5h-6a1.5 1.5 0 0 1-1.5-1.5V15a.75.75 0 0 0-1.5 0v3.75a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V5.25a3 3 0 0 0-3-3h-6a3 3 0 0 0-3 3V9A.75.75 0 0 0 9 9V5.25a1.5 1.5 0 0 1 1.5-1.5h6ZM5.78 8.47a.75.75 0 0 0-1.06 0l-3 3a.75.75 0 0 0 0 1.06l3 3a.75.75 0 0 0 1.06-1.06l-1.72-1.72H15a.75.75 0 0 0 0-1.5H4.06l1.72-1.72a.75.75 0 0 0 0-1.06Z" clipRule="evenodd" />
    </svg>
);

function NavItem({ href, active, children, icon: Icon }) {
    return (
        <Link
            href={href}
            className={[
                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                active
                    ? 'bg-indigo-600/20 text-indigo-300'
                    : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100',
            ].join(' ')}
        >
            <Icon className="w-5 h-5 shrink-0" />
            <span>{children}</span>
        </Link>
    );
}

function NavSection({ label, children }) {
    return (
        <div className="mb-1">
            <p className="px-3 mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-slate-600">
                {label}
            </p>
            <div className="space-y-0.5">{children}</div>
        </div>
    );
}

function getInitials(name) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

export default function AppLayout({ title, children }) {
    const { auth, notificaciones_no_leidas } = usePage().props;
    const usuario = auth.user;
    const initials = getInitials(usuario.name);
    const esAdmin = usuario.roles?.some((r) => r.name === 'admin');
    const esRrhh = usuario.roles?.some((r) => r.name === 'rrhh');

    return (
        <div className="flex h-screen overflow-hidden bg-slate-50">
            <CargandoOverlay />
            {/* ── Sidebar ── */}
            <aside className="w-60 shrink-0 flex flex-col bg-slate-900">
                {/* Branding */}
                <div className="h-14 flex items-center px-4 border-b border-slate-800/80 justify-center my-1">
                    <Link href={route('inicio')} className="flex items-center gap-2.5 group">
                        <img src="/images/logo2.png" alt="Logo" className="w-40"  />
                    </Link>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-3 py-4 space-y-4 overflow-y-auto my-1">
                    <NavSection label="Principal">
                        <NavItem
                            href={route('inicio')}
                            active={route().current('inicio')}
                            icon={IconHome}
                        >
                            Inicio
                        </NavItem>
                        <NavItem
                            href={route('solicitudes.index')}
                            active={route().current('solicitudes.*')}
                            icon={IconInbox}
                        >
                            Mis solicitudes
                        </NavItem>
                        {esRrhh && (
                            <NavItem
                                href={route('rrhh.comisiones')}
                                active={route().current('rrhh.*')}
                                icon={IconUsers}
                            >
                                Comisiones RR. HH.
                            </NavItem>
                        )}
                    </NavSection>

                    <NavSection label="Nueva solicitud">
                        <NavItem
                            href={route('oficina.crear')}
                            active={route().current('oficina.*')}
                            icon={IconBuilding}
                        >
                            Oficina
                        </NavItem>
                        <NavItem
                            href={route('viaticos.crear')}
                            active={route().current('viaticos.*')}
                            icon={IconMap}
                        >
                            Viáticos
                        </NavItem>
                    </NavSection>

                    <NavSection label="Configuración">
                        {esAdmin && (
                            <NavItem
                                href={route('usuarios.index')}
                                active={route().current('usuarios.*')}
                                icon={IconUsers}
                            >
                                Usuarios
                            </NavItem>
                        )}
                        <NavItem
                            href={route('parametros.index')}
                            active={route().current('parametros.*')}
                            icon={IconCog}
                        >
                            Parámetros
                        </NavItem>
                        <NavItem
                            href={route('profile.edit')}
                            active={route().current('profile.*')}
                            icon={IconUser}
                        >
                            Mi perfil
                        </NavItem>
                    </NavSection>
                </nav>

                {/* User footer */}
                <div className="border-t border-slate-800/80 p-3 space-y-1">
                    <div className="flex items-center gap-3 px-2 py-1.5 rounded-lg">
                        <div className="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-bold shrink-0 select-none">
                            {initials}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-white leading-tight truncate">{usuario.name}</p>
                            <p className="text-xs text-slate-500 truncate">{usuario.email}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => router.post(route('logout'))}
                        className="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-all duration-150"
                    >
                        <IconLogout className="w-5 h-5 shrink-0" />
                        Cerrar sesión
                    </button>
                </div>
            </aside>

            {/* ── Main area ── */}
            <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
                {/* Top bar */}
                <header className="h-14 shrink-0 bg-white border-b border-slate-200 flex items-center px-6">
                    {title && (
                        <h1 className="text-sm font-semibold text-slate-800">{title}</h1>
                    )}
                    <div className="flex items-center gap-2 ml-auto">
                        <PanelNotificaciones noLeidasIniciales={notificaciones_no_leidas} />
                        <div
                            className="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-bold select-none"
                            title={usuario.name}
                        >
                            {initials}
                        </div>
                    </div>
                </header>

                {/* Page content */}
                <main className="flex-1 min-h-0 overflow-y-auto bg-slate-50">
                    {children}
                </main>
            </div>
        </div>
    );
}
