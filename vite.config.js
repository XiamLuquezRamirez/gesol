import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// En produccion la app se sirve desde la subcarpeta /gesol/ del dominio.
// `base` de Vite = prefijo + 'build/' para que TANTO el manifest COMO los
// imports dinamicos de los chunks apunten a /gesol/build/assets/...
// Para las imagenes de public/ (que NO estan en build/) se usa aparte la
// variable VITE_APP_BASE (el prefijo pelado /gesol/), inyectada al cliente.
// Compilar para produccion con:  MSYS_NO_PATHCONV=1 ASSET_BASE=/gesol/ npm run build
const prefijo = (process.env.ASSET_BASE || '/').replace(/\/+$/, '/'); // normaliza a terminar en un solo '/'
const prefijoLimpio = prefijo === '' ? '/' : (prefijo.startsWith('/') ? prefijo : '/' + prefijo);
const base = prefijoLimpio.replace(/\/$/, '') + '/build/';  // /gesol/build/  (o /build/ en local)
process.env.VITE_APP_BASE = prefijoLimpio;                  // /gesol/ (o / en local) para imagenes

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
