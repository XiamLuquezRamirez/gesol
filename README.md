# Gesol

Sistema de gestión de solicitudes internas. Laravel 10 + Inertia.js + React 18.

---

## Requisitos

- PHP 8.2+
- Composer 2+
- Node 18+ (recomendado 20+ o 22+)
- MariaDB 10.6+ (o MySQL 8+)
- npm 9+

---

## Instalación

### 1. Clonar y dependencias

```bash
git clone <repo-url> gesol
cd gesol

composer install
npm install
```

### 2. Configurar entorno

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con los datos de la base de datos:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gesol
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Base de datos

Crear la base de datos en MariaDB/MySQL:

```sql
CREATE DATABASE gesol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ejecutar migraciones y seeders:

```bash
php artisan migrate --seed
```

Esto crea las tablas, los cinco roles del sistema y el usuario administrador demo (`admin@demo.test` / `password`).

### 4. Arrancar en desarrollo

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

Abrir `http://127.0.0.1:8000` e iniciar sesión con `admin@demo.test`.

---

## Despliegue en producción

```bash
composer install --no-dev --optimize-autoloader
npm run build          # genera public/build/
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Importante:** subir `public/build/` al servidor. **No subir `public/hot`** (es el socket de Vite en desarrollo).

---

## Convenciones

Ver `ESTANDARES_NOMENCLATURA.md` y `CLAUDE.md`.
