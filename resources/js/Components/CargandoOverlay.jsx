import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Overlay global de "procesando". Se muestra durante cualquier petición Inertia
 * (navegación o envío de formularios/acciones), suscribiéndose a los eventos
 * globales del router. Complementa la barra de progreso superior de Inertia con
 * un velo + spinner que deja claro que hay una solicitud en curso.
 *
 * Un pequeño retardo evita el parpadeo en respuestas instantáneas.
 */
const RETARDO_MS = 180;

export default function CargandoOverlay() {
    const [visible, setVisible] = useState(false);
    const temporizador = useRef(null);

    useEffect(() => {
        const limpiarTemporizador = () => {
            if (temporizador.current) {
                clearTimeout(temporizador.current);
                temporizador.current = null;
            }
        };

        const alIniciar = router.on('start', () => {
            limpiarTemporizador();
            temporizador.current = setTimeout(() => setVisible(true), RETARDO_MS);
        });

        const alTerminar = router.on('finish', () => {
            limpiarTemporizador();
            setVisible(false);
        });

        return () => {
            limpiarTemporizador();
            alIniciar();   // los listeners de Inertia devuelven su propia función de baja
            alTerminar();
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/20 backdrop-blur-[1px]"
            aria-live="polite"
            aria-busy="true"
            role="status"
        >
            <div className="flex items-center gap-3 rounded-xl bg-white px-5 py-4 shadow-lg border border-slate-200">
                <svg className="w-6 h-6 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-90" fill="currentColor"
                        d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span className="text-sm font-medium text-slate-700">Procesando…</span>
            </div>
        </div>
    );
}
