# CLAUDE.md — Gesol

Guía de referencia rápida para Claude Code. Lee este archivo antes de modificar cualquier cosa.

---

## Stack y versiones (NO cambiar sin aviso)

| Componente | Versión |
|---|---|
| PHP | 8.2.12 |
| Laravel | 10.50.2 |
| Node | 22.14.0 |
| Laravel Breeze | 1.29.0 (línea 1.x, compatible con Laravel 10) |
| Inertia Laravel | 0.6.11 |
| React | 18 |
| Vite | integrado en Breeze |
| Tailwind CSS | integrado en Breeze |
| spatie/laravel-permission | 6.0.0 |
| Base de datos | MariaDB (driver `mysql`) |

> **Autenticación por sesión** (Breeze/Inertia). Sin Sanctum tokens ni API REST.

---

## Convenciones de nombres

Seguir `ESTANDARES_NOMENCLATURA.md` al pie de la letra. Resumen:

- Dominio en **español**; infraestructura del framework en inglés (`id`, `created_at`, `updated_at`, `deleted_at`, sufijos `_type`/`_id`).
- Identificadores **sin tildes ni ñ** (ASCII). Comentarios y textos de UI sí con ortografía correcta.
- El modelo de usuario es **`Usuario`** y la tabla **`usuarios`** (no `User`/`users`).

---

## Estructura de carpetas relevante

```
app/
  Http/
    Controllers/
      Auth/          # Controladores de autenticación (Breeze, usan Usuario)
      ProfileController.php
    Middleware/
      HandleInertiaRequests.php  # Comparte auth.user + roles globalmente
  Models/
    Usuario.php      # Modelo de usuario (tabla: usuarios)

database/
  factories/
    UsuarioFactory.php
  migrations/
  seeders/
    RolesSeeder.php
    AdminSeeder.php
    DatabaseSeeder.php

resources/
  js/
    Layouts/
      AppLayout.jsx      # Layout autenticado en español (barra + usuario + logout)
      GuestLayout.jsx    # Layout para páginas de invitado (login, registro)
    Pages/
      Auth/              # Login, Register, ForgotPassword, etc. (en español)
      Inicio/
        Index.jsx        # Página de inicio autenticada (placeholder)
      Profile/
        Edit.jsx
  lang/
    es/                  # Traducciones de validación, auth y passwords
    en/

config/
  auth.php               # providers.users.model => App\Models\Usuario::class
  app.php                # locale => 'es'
```

---

## Cómo arrancar en desarrollo

```bash
# Terminal 1 — servidor PHP
php artisan serve

# Terminal 2 — Vite (hot reload)
npm run dev
```

La aplicación queda en `http://127.0.0.1:8000`.

---

## Cómo compilar para producción

```bash
npm run build
```

Genera `public/build/`. **No subir `public/hot`** al repositorio ni al servidor.

---

## Roles del sistema (seed)

Los cinco roles creados por `RolesSeeder`:

- `lider_area`
- `lider_comite`
- `rrhh`
- `contabilidad_lider`
- `contador`

Usuario demo: `admin@demo.test` / `password` (tiene todos los roles).

---

## Fuera de alcance hasta ahora

El motor de solicitudes (`Solicitud`, `MotorWorkflow`, transiciones), migraciones de dominio, permisos finos y notificaciones. Ver `PROMPT_CLAUDE_CODE.md`.
