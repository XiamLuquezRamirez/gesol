import { Head, router } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import PrimaryButton from '@/Components/PrimaryButton';

/**
 * Pantalla propia para la sesion expirada (error 419 / CSRF).
 * Aparece cuando la pantalla estuvo mucho tiempo inactiva y el token caduco.
 * Sustituye a la pagina generica "Page Expired" de Laravel por un mensaje claro
 * en espanol, con un boton para volver a iniciar sesion.
 */
export default function SesionExpirada() {
    // Visita completa al login (no una visita SPA): reinicia el ciclo de sesion/token.
    const irALogin = () => {
        window.location.href = route('login');
    };

    return (
        <GuestLayout>
            <Head title="Sesión expirada" />

            <div className="text-center py-2">
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" strokeWidth="1.8" className="h-7 w-7 text-amber-600">
                        <circle cx="12" cy="12" r="9" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5v5l3 2" />
                    </svg>
                </div>

                <h1 className="text-lg font-semibold text-slate-800">Tu sesión expiró</h1>
                <p className="mt-2 text-sm text-slate-500">
                    Por seguridad, la sesión se cerró tras un periodo de inactividad.
                    Vuelve a iniciar sesión para continuar; no perdiste tu información guardada.
                </p>

                <div className="mt-6">
                    <PrimaryButton onClick={irALogin} className="w-full justify-center">
                        Volver a iniciar sesión
                    </PrimaryButton>
                </div>
            </div>
        </GuestLayout>
    );
}
