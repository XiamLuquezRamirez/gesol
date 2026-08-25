# Despliegue de Gesol a producción

Guía para poner Gesol (Laravel 10 + Inertia/React + MariaDB) en un servidor de producción.

---

## 1. Requisitos del servidor

| Componente | Versión mínima |
|---|---|
| PHP | 8.2 (con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `ctype`, `fileinfo`, `gd` o `imagick` para PDF, `zip`) |
| Composer | 2.x |
| Node.js | 22.x (solo para compilar assets; puede compilarse en local y subir `public/build/`) |
| MariaDB / MySQL | MariaDB 10.4+ / MySQL 5.7+ |
| Servidor web | Apache o Nginx apuntando a la carpeta **`public/`** |

> **Autenticación por sesión** (Breeze/Inertia). No requiere Redis ni colas: `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`.

---

## 2. Configuración del entorno (`.env`)

1. Copia `.env.production.example` a `.env` en el servidor.
2. Rellena: `APP_URL` (con https), datos de `DB_*`, y `MAIL_*` (para el envío de liquidaciones/anexos por correo).
3. Genera la clave de la app **en el servidor**:
   ```bash
   php artisan key:generate
   ```
4. Verifica que quede: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`.

> **Nunca** subas el `.env` real al repositorio.

---

## 3. Base de datos

### Opción A — Base nueva (recomendada para primer despliegue)
Deja que las migraciones creen el esquema:
```bash
php artisan migrate --force
```
Luego siembra **solo los catálogos** (idempotentes):
```bash
php artisan db:seed --class=RolesSeeder --force
php artisan db:seed --class=TipoSolicitudSeeder --force
php artisan db:seed --class=TarifaViaticosSeeder --force
```
Los empleados/áreas/municipios se cargan aparte (import o el comando `gesol:importar-empleados`).

### Opción B — Importar un dump SQL existente
Si subes un dump de MariaDB por phpMyAdmin/cPanel y aparece:
> `#1071 - Specified key was too long; max key length is 767 bytes`

es porque el dump usa `utf8mb4` (4 bytes) con columnas `VARCHAR(255) UNIQUE` y el servidor limita los índices a 767 bytes. Soluciones:
- **Recomendado:** convertir el dump a `utf8` (3 bytes → 255×3 = 765 < 767). Reemplaza en el .sql `utf8mb4` → `utf8` y `utf8mb4_unicode_ci` → `utf8_unicode_ci`.
- **Alternativa (conserva emojis):** añadir `ROW_FORMAT=DYNAMIC` a cada `CREATE TABLE` (sube el límite a 3072 bytes; requiere InnoDB Barracuda + `innodb_large_prefix=ON`).

> ⚠️ **No corras nunca en producción** `AdminSeeder` ni `UsuariosDemoSeeder` ni `db:seed` (sin `--class`): crean usuarios con contraseñas conocidas (`admin@demo.test` / `password`, y 5 usuarios `*.demo.test`). Crea los usuarios reales manualmente o con un seeder propio.

---

## 4. Assets del frontend (Vite)

Compila los assets optimizados (en el servidor **o** en local y sube `public/build/`):
```bash
npm ci
npm run build
```
- Genera `public/build/` con el `manifest.json` y los chunks con hash.
- **Elimina `public/hot`** si existe (marca el modo dev de Vite y rompe producción):
  ```bash
  rm -f public/hot
  ```
- No subas `node_modules/` al servidor si compilas en local.

---

## 5. Cachés de Laravel (optimización)

En producción se cachean configuración, rutas y vistas:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link   # enlaza storage/app/public -> public/storage
```
> Tras cada cambio de `.env` hay que volver a correr `php artisan config:cache` (o `config:clear`), porque con la config cacheada el `.env` deja de leerse en caliente.

---

## 6. Permisos de archivos

El usuario del servidor web debe poder escribir en:
```bash
chmod -R 775 storage bootstrap/cache
# Ajusta el propietario al usuario de Apache/Nginx (p. ej. www-data):
# chown -R www-data:www-data storage bootstrap/cache
```

---

## 7. Servidor web

Configura el *document root* del dominio hacia la carpeta **`public/`** del proyecto (nunca a la raíz). Laravel trae `public/.htaccess` para Apache; para Nginx usa el bloque estándar de Laravel (`try_files $uri $uri/ /index.php?$query_string;`).

---

## 8. Despliegue automatizado

Para despliegues siguientes, usa el script incluido (revisa que la rama sea `main`):
```bash
bash deploy.sh
```
Hace: mantenimiento → `git pull` → `composer install --no-dev` → `npm ci && npm run build` → `migrate --force` → seeders de catálogos → recache → `up`.

---

## 9. Checklist final (antes de dar por live)

- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generada, `APP_URL` con https.
- [ ] `DB_*` apuntan a la base de producción y conectan.
- [ ] `MAIL_*` configurado y probado (envío de una liquidación de prueba).
- [ ] `composer install --no-dev --optimize-autoloader` ejecutado.
- [ ] `public/build/` presente y **`public/hot` ausente**.
- [ ] `config:cache`, `route:cache`, `view:cache`, `storage:link` corridos.
- [ ] Permisos de `storage/` y `bootstrap/cache/` correctos.
- [ ] Document root apunta a `public/`.
- [ ] **No** existen usuarios demo (`*.demo.test`) ni `admin@demo.test/password`.
- [ ] Roles del sistema creados (`lider_area`, `lider_comite`, `rrhh`, `contabilidad_lider`, `contador`) y al menos un usuario real con su rol.
- [ ] Migración de `ajustes_comision` y columna `asignaciones_viaticos.ajuste_comision_id` aplicadas (feature de ajustes de viáticos).
- [ ] `TipoSolicitudSeeder` re-sembrado (transiciones con notificación a RR. HH. al enviar a gerencia / cerrar).
- [ ] HTTPS forzado y certificado válido.
