import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// En produccion la app se sirve desde la subcarpeta /gesol/ del dominio
// (con el .htaccess de la raiz redirigiendo a public/). Vite debe generar las
// rutas de los assets con ese prefijo. En desarrollo local se deja el default.
// Compilar para produccion con:  ASSET_BASE=/gesol/ npm run build
const base = process.env.ASSET_BASE
    ? process.env.ASSET_BASE + 'build/'
    : '/build/';

export default defineConfig({
    base,
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
});
