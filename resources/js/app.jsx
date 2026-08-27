import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Despliegue en subcarpeta: en algunos casos Inertia/Ziggy duplican el prefijo
// del subdirectorio en la URL de las peticiones (p. ej. /gesol/gesol/parametros).
// Este interceptor colapsa cualquier segmento repetido consecutivo en la ruta
// antes de cada visita, dejando la URL correcta. Inofensivo en la raiz ('/').
router.on('before', (event) => {
    const visit = event.detail.visit;
    if (!visit || !visit.url) return;
    // Colapsar "/x/x/" -> "/x/" en el pathname (repeticiones consecutivas).
    const antes = visit.url.pathname;
    const despues = antes.replace(/\/([^/]+)\/\1(\/|$)/g, '/$1$2');
    if (despues !== antes) {
        visit.url.pathname = despues;
    }
});

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
