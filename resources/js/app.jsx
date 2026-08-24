import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
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
