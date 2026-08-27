import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Despliegue en subcarpeta (/gesol/): Ziggy por defecto genera rutas relativas
// que Inertia combina con la ubicacion actual, duplicando el prefijo
// (/gesol/gesol/login). Forzamos route() a devolver URLs ABSOLUTAS (con dominio),
// que Inertia usa tal cual sin duplicar. Solo aplica cuando @routes ya definio
// el global window.route; en local sigue funcionando igual.
if (typeof window !== 'undefined' && typeof window.route === 'function') {
    const rutaOriginal = window.route;
    window.route = (name, params, absolute = true, config) =>
        rutaOriginal(name, params, absolute, config);
}

//agregar nombre de dominio en el title
const appName = import.meta.env.VITE_APP_DOMAIN || 'GeSol';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4f46e5',   // indigo-600, coherente con la UI
        delay: 120,          // aparece rapido en peticiones que tardan
        showSpinner: true,   // ademas de la barra, un spinner en la esquina
    },
});
