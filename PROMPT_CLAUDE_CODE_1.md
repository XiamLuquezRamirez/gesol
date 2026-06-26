# Prompt para Claude Code — Sistema de Gestión de Solicitudes Internas

> Copia este archivo completo como primer mensaje en Claude Code (o guárdalo como `PROMPT.md` en la raíz y referéncialo). Está pensado para que Claude Code construya el proyecto por fases. **No generes todo el código de una vez: sigue el orden de fases y detente al final de cada fase para que yo lo revise.**

---

## 1. Contexto y objetivo

Construye un sistema web interno para una **empresa privada** que gestiona dos procesos de solicitud con flujo de aprobación multi-etapa:

1. **Solicitud de compra de elementos de oficina** — un líder de área solicita la compra de elementos (mouse, teclado, marcador) o servicios (mantenimiento de aire) para un integrante de su equipo. RRHH verifica que la compra sea necesaria, Contabilidad la aprueba y la misma Contabilidad registra el pago.
2. **Solicitud de viáticos** — el líder de un comité solicita viáticos para un viaje a otro municipio. RRHH valida, la líder de Contabilidad aprueba el monto global, y un contador liquida la distribución por persona y por rubro (desayuno, almuerzo, cena, merienda, gasolina).

**Insight arquitectónico central (respétalo):** aunque son dos procesos distintos, comparten el mismo patrón —*una solicitud que recorre estados según una matriz de transiciones, donde cada transición exige un rol*—. NO construyas dos sistemas separados ni dos motores de estado. Construye **un único motor de solicitudes** (`Request` + `WorkflowService`) que interpreta matrices de transición almacenadas como datos. Cada proceso es un "tipo de solicitud" configurado, no código duplicado. Agregar un tercer proceso en el futuro debe ser insertar filas de configuración, no escribir lógica nueva.

---

## 2. Stack técnico

**Aplicación monolítica con Laravel como backend completo e Inertia + React como capa de vista. Un solo despliegue, sin API REST separada, sin CORS, sin tokens.**

- **Backend:** **Laravel 11** — rutas, controladores, modelos Eloquent, validación, autenticación. Es el cerebro completo de la app.
  - Capa de vista: **Inertia.js** (adaptador `inertiajs/inertia-laravel`). Los controladores devuelven páginas React con props vía `Inertia::render(...)`, igual que devolverían una vista Blade.
  - Autenticación: **sesión nativa de Laravel** (starter kit **Laravel Breeze con stack Inertia + React**). NADA de Sanctum tokens ni API auth.
  - Roles y permisos: **spatie/laravel-permission**.
  - Estados: **enums de PHP 8.1+**.
  - PHP 8.2+.
- **Frontend:** **React 18** como capa de vista de Inertia, **dentro del mismo proyecto Laravel** (en `resources/js`). NO es un proyecto aparte.
  - Routing: lo maneja **Laravel** (`routes/web.php`). En React se navega con el componente `<Link>` de Inertia, no con react-router.
  - Datos: llegan como **props** desde el controlador; no hay fetching manual ni react-query. Para acciones se usa `useForm`/`router` de `@inertiajs/react`.
  - Estilos: **Tailwind CSS**.
- **Empaquetador:** **Vite integrado en Laravel** (directiva `@vite` + `laravel-vite-plugin`). Compila `resources/js`. No es un host separado.
- **Base de datos:** **MariaDB** (driver `mysql` de Laravel).

**Por qué este stack (contexto del proyecto):** versiones previas usaron una SPA React desacoplada con API + Sanctum, y el despliegue a producción fue problemático (CORS, doble artefacto, 404 al refrescar rutas del cliente, build separado). Inertia elimina todo eso: el routing y la auth viven en Laravel, y la app se despliega como **un solo artefacto** — código Laravel + assets compilados en `public/build`. Esto es deliberado; no reintroduzcas una API REST ni un front separado.

**Nota de despliegue a tener presente (documéntala en el README):** en hosting sin Node, NO se corre `npm run build` en el servidor. Se compila en local o CI (`npm run build`) y se sube la carpeta `public/build` ya generada junto al código. Asegúrate de que `public/hot` no exista en producción y que `APP_URL` esté bien configurada.

---

## 3. Modelo de datos (migraciones)

Usa nombres de tabla en plural snake_case. Todas con `id` bigint autoincrement y timestamps salvo que se indique. Llaves foráneas con `constrained()` y `onDelete('cascade')` donde aplique.

### 3.1 Núcleo (compartido por ambos procesos)

**`areas`**
- `name` string
- `description` string nullable

**`request_types`** — define cada proceso y su máquina de estados como datos
- `key` string unique — ej. `office_supplies`, `per_diem`
- `name` string
- `initial_status` string
- `statuses` json — lista de estados válidos
- `transitions` json — matriz de transiciones (ver §4)

**`requests`** — tabla central polimórfica
- `request_type_id` foreignId constrained
- `requester_id` foreignId → users
- `area_id` foreignId → areas nullable
- `requestable_type` string / `requestable_id` bigint — relación polimórfica `morphTo` hacia la cabecera específica del proceso
- `status` string — estado actual
- `radicado` string unique — número de radicado generado (formato `{KEY}-{AAAA}-{secuencia}`, ej. `OFI-2026-00012`)
- `total` decimal(14,2) default 0 — calculado, nunca capturado a mano
- index en (`request_type_id`, `status`)

**`request_transitions`** — auditoría de cada cambio de estado
- `request_id` foreignId constrained cascade
- `from_status` string nullable
- `to_status` string
- `action` string — la acción ejecutada (`enviar`, `aprobar`, etc.)
- `user_id` foreignId → users
- `comment` text nullable
- `metadata` json nullable — para datos del paso (ej. valor pagado, comprobante)
- `created_at` timestamp (sin updated_at)

### 3.2 Proceso 1 — Elementos de oficina

**`office_supply_requests`** (cabecera, relación polimórfica con `requests`)
- `beneficiary_id` foreignId → users — el integrante que necesita el elemento (distinto del solicitante)
- `urgency` enum string: `baja`, `media`, `alta`
- `justification` text
- `total` decimal(14,2) default 0
- Campos de pago (se llenan en la transición a `pagada`, todos nullable):
  - `valor_pagado` decimal(14,2) nullable
  - `fecha_pago` date nullable
  - `comprobante` string nullable — ruta/numero de comprobante

**`office_supply_items`**
- `office_supply_request_id` foreignId constrained cascade
- `name` string
- `category` enum string: `producto`, `servicio`
- `quantity` integer default 1
- `costo_estimado` decimal(14,2) — unitario
- `subtotal` decimal(14,2) — `quantity * costo_estimado`, calculado al guardar
- `notes` string nullable

### 3.3 Proceso 2 — Viáticos

**`per_diem_requests`** (cabecera, relación polimórfica con `requests`)
- `committee_name` string
- `destination_municipality` string
- `reason` text
- `depart_date` date
- `return_date` date
- `total` decimal(14,2) default 0 — suma de allocations

**`per_diem_travelers`**
- `per_diem_request_id` foreignId constrained cascade
- `user_id` foreignId → users
- `role_in_committee` string nullable

**`per_diem_allocations`** — desglose fino por viajero × rubro
- `per_diem_traveler_id` foreignId constrained cascade
- `rubro` enum string: `desayuno`, `almuerzo`, `cena`, `merienda`, `gasolina`
- `unit_value` decimal(14,2)
- `days` integer default 1
- `subtotal` decimal(14,2) — `unit_value * days`, calculado al guardar

**`per_diem_rates`** (catálogo opcional de política interna — valores sugeridos, NO topes legales)
- `rubro` string unique
- `suggested_value` decimal(14,2)

> **Regla de oro (impleméntala en los modelos):** `requests.total`, `office_supply_requests.total` y `per_diem_requests.total` **nunca** se capturan manualmente. Se recalculan como la suma de sus ítems/allocations cada vez que cambian (usa eventos de modelo `saved`/`deleted` en los hijos, o un método `recalculateTotal()` en la cabecera invocado por el servicio). El total de la cabecera específica se propaga a `requests.total`.

---

## 4. Matrices de workflow (datos, no código)

Guárdalas en `request_types.transitions` vía seeder. El `WorkflowService` las interpreta; **no escribas `if/switch` por proceso**.

### 4.1 `office_supplies`
```json
{
  "initial": "borrador",
  "statuses": ["borrador","enviada","verificada","aprobada","pagada","cerrada","rechazada"],
  "transitions": [
    {"from":"borrador","action":"enviar","to":"enviada","roles":["lider_area"]},
    {"from":"enviada","action":"verificar","to":"verificada","roles":["rrhh"]},
    {"from":"enviada","action":"devolver","to":"borrador","roles":["rrhh"]},
    {"from":"verificada","action":"aprobar","to":"aprobada","roles":["contabilidad_lider"]},
    {"from":"verificada","action":"rechazar","to":"rechazada","roles":["contabilidad_lider"]},
    {"from":"aprobada","action":"pagar","to":"pagada","roles":["contabilidad_lider"]},
    {"from":"pagada","action":"cerrar","to":"cerrada","roles":["contabilidad_lider","lider_area"]}
  ]
}
```

### 4.2 `per_diem`
La solicitud del líder de comisión llega **directo a la líder de contabilidad** (RRHH NO es compuerta aquí). Al **aprobar** el monto, se notifica a RRHH de forma **informativa** (quiénes salen y por cuánto tiempo) y pasa al contador para liquidar.
```json
{
  "initial": "borrador",
  "statuses": ["borrador","enviada","aprobada_monto","liquidada","cerrada","rechazada"],
  "transitions": [
    {"from":"borrador","action":"enviar","to":"enviada","roles":["lider_comite"]},
    {"from":"enviada","action":"aprobar","to":"aprobada_monto","roles":["contabilidad_lider"],"notify":["rrhh"]},
    {"from":"enviada","action":"rechazar","to":"rechazada","roles":["contabilidad_lider"]},
    {"from":"enviada","action":"devolver","to":"borrador","roles":["contabilidad_lider"]},
    {"from":"aprobada_monto","action":"liquidar","to":"liquidada","roles":["contador"]},
    {"from":"liquidada","action":"cerrar","to":"cerrada","roles":["contador","lider_comite"]}
  ]
}
```
**Extensión de la matriz — llave `notify`:** una transición puede declarar `notify` con una lista de roles que reciben una notificación **informativa** (no son actores, no aprueban ni bloquean). El `WorkflowService` debe, además de avisar al actor del siguiente paso, enviar una notificación a los roles en `notify`. Para la aprobación de viáticos, la notificación a `rrhh` incluye en su metadata la lista de viajeros (`per_diem_travelers`) y las fechas `depart_date`/`return_date`.

**Reglas de negocio adicionales:**
- La acción `liquidar` (viáticos) solo es válida si existen `per_diem_allocations` para todos los viajeros; valida en el FormRequest/servicio.
- La acción `pagar` (oficina) recibe `valor_pagado`, `fecha_pago`, `comprobante` y los guarda tanto en la cabecera como en `metadata` de la transición.
- Toda edición de cabecera/ítems solo se permite en estado `borrador` (o tras una `devolver`).

---

## 5. Roles

Crea estos roles con spatie/laravel-permission (seeder):
- `lider_area` — crea solicitudes de oficina.
- `lider_comite` — crea solicitudes de viáticos.
- `rrhh` — verifica/valida en el proceso de **oficina** (es compuerta). En **viáticos NO es compuerta**: solo recibe una notificación informativa cuando contabilidad aprueba (quiénes salen de comisión y por cuánto tiempo).
- `contabilidad_lider` — aprueba y registra pago (oficina) / aprueba monto (viáticos).
- `contador` — liquida viáticos.

Un usuario puede tener varios roles. La autorización de cada transición se hace comparando los roles del usuario con `transition.roles`. Implementa esto en una **Policy** (`RequestPolicy@transition`) que delega en el `WorkflowService`.

---

## 6. WorkflowService (núcleo)

`app/Services/WorkflowService.php` con al menos:

- `availableActions(Request $request, User $user): array` — devuelve las transiciones cuyo `from` == estado actual y cuyo `roles` intersecta con los roles del usuario. Estas acciones se pasan como prop a la página de detalle para pintar la botonera.
- `can(Request $request, string $action, User $user): bool`
- `apply(Request $request, string $action, User $user, ?string $comment = null, array $metadata = []): Request` — valida con `can()`, aplica reglas de negocio del paso, actualiza `status`, crea el registro en `request_transitions`, dispara una notificación al/los responsable(s) del siguiente paso, y retorna la solicitud. Todo dentro de una transacción DB.

Lanza una excepción de dominio (`TransitionNotAllowedException`) si la acción no es válida; en el contexto Inertia, redirige de vuelta con un error de sesión (`->withErrors(...)`) en lugar de un 422 JSON.

Notificaciones: usa Laravel Notifications (canal `database` por ahora). En cada transición avisa (a) a los usuarios con el rol del siguiente paso y (b) a los roles declarados en la llave `notify` de la transición (notificación informativa; ver §4.2 para el caso de RRHH en viáticos). Expón el conteo de no leídas como prop compartida de Inertia (ver §7.3).

---

## 7. Capa web: rutas, controladores Inertia y páginas

**No hay API REST.** Los controladores devuelven páginas Inertia con props o redirecciones. Todo en `routes/web.php`, protegido por el middleware `auth` (sesión).

### 7.1 Rutas (`routes/web.php`)
```
// Auth la provee Breeze (login, logout, etc.)

GET    /                                 -> redirige a /solicitudes
GET    /solicitudes                      -> RequestController@index   (Inertia: Solicitudes/Index)
                                            filtros por query: ?tab=mias|pendientes, ?type, ?status
GET    /solicitudes/{request}            -> RequestController@show     (Inertia: Solicitudes/Show)
POST   /solicitudes/{request}/transicion -> RequestController@transition (ejecuta WorkflowService, redirect back)

// Proceso 1 — oficina
GET    /oficina/crear                    -> OfficeSupplyController@create  (Inertia: Oficina/Crear)
POST   /oficina                          -> OfficeSupplyController@store   (redirect a show)
GET    /oficina/{request}/editar         -> OfficeSupplyController@edit    (solo borrador)
PUT    /oficina/{request}                -> OfficeSupplyController@update

// Proceso 2 — viáticos
GET    /viaticos/crear                   -> PerDiemController@create       (Inertia: Viaticos/Crear)
POST   /viaticos                         -> PerDiemController@store
GET    /viaticos/{request}/editar        -> PerDiemController@edit
PUT    /viaticos/{request}               -> PerDiemController@update
GET    /viaticos/{request}/liquidar      -> PerDiemController@liquidacion  (Inertia: Viaticos/Liquidacion)
PUT    /viaticos/{request}/allocations   -> PerDiemController@updateAllocations
```

### 7.2 Patrón de controlador (ejemplo)
```php
public function index(Request $req)
{
    $solicitudes = SolicitudQuery::for(auth()->user(), $req->query());
    return Inertia::render('Solicitudes/Index', [
        'solicitudes' => SolicitudResource::collection($solicitudes),
        'filtros'     => $req->only('tab','type','status'),
    ]);
}

public function show(SolicitudModel $request, WorkflowService $wf)
{
    return Inertia::render('Solicitudes/Show', [
        'solicitud' => new SolicitudDetalleResource($request->load('requestable','transitions.user')),
        'acciones'  => $wf->availableActions($request, auth()->user()),
    ]);
}
```
Usa **API Resources** igual que antes, solo que ahora alimentan props de Inertia en lugar de respuestas JSON. Valida con **FormRequests**.

### 7.3 Props compartidas (`HandleInertiaRequests` middleware)
Comparte globalmente: `auth.user` (con sus roles), y `notificaciones_no_leidas` (conteo). Así el layout siempre tiene el usuario y el badge de notificaciones sin pedirlos en cada página.

---

## 8. Frontend (React dentro de Laravel, vía Inertia)

Estructura en `resources/js`:
```
app.jsx                 bootstrap de Inertia + React
/Layouts
  AppLayout.jsx          nav, usuario actual, badge de notificaciones
/Pages
  /Auth                  (las que trae Breeze)
  /Solicitudes
    Index.jsx            bandejas (mías / pendientes)
    Show.jsx             detalle + timeline + botonera dinámica
  /Oficina
    Crear.jsx            formulario + tabla de ítems
  /Viaticos
    Crear.jsx            cabecera + viajeros
    Liquidacion.jsx      grid viajeros × rubros
/Components
  EstadoBadge.jsx, Timeline.jsx, AccionModal.jsx, Field.jsx, MoneyInput.jsx
/lib
  format.js              moneda COP, fechas
```

Convenciones Inertia (úsalas, no reintroduzcas patrones de SPA):
- Datos llegan como **props** de cada página. No hay `useEffect` + fetch ni react-query.
- Navegación con `<Link href="...">` de `@inertiajs/react`.
- Formularios y acciones con `useForm` de `@inertiajs/react` (maneja estado, errores de validación de Laravel y envío). Ejemplo: el modal de acción hace `form.post('/solicitudes/'+id+'/transicion', {...})`.
- Los errores de validación de los FormRequests aparecen automáticamente en `form.errors`.

Pantallas mínimas:
1. **Login** (de Breeze).
2. **Index / Bandejas** — dos pestañas: "Mis solicitudes" y "Pendientes" (donde puedo actuar, `?tab=pendientes`). Filas con radicado, tipo, estado (badge de color), total, fecha.
3. **Crear oficina** — cabecera (beneficiario, área, urgencia, justificación) + tabla dinámica de ítems (nombre, categoría producto/servicio, cantidad, costo estimado → subtotal y total calculados en vivo en el cliente para feedback, pero el servidor recalcula y es la fuente de verdad).
4. **Crear viáticos** — cabecera (comité, municipio destino, motivo, fechas) + lista de viajeros (selector de usuarios).
5. **Detalle (Show)** — datos + **timeline de transiciones** + botonera generada desde la prop `acciones`. Cada acción abre un modal con comentario opcional; `pagar` pide valor/fecha/comprobante.
6. **Liquidación de viáticos** (rol contador, estado `aprobada_monto`) — grid editable viajeros (filas) × rubros (columnas) con valor y días por celda; subtotales por viajero y total general; guarda con `PUT /viaticos/{id}/allocations` y luego habilita la acción `liquidar`.

UI limpia con Tailwind; estados con colores consistentes (borrador=gris, en proceso=azul/morado, aprobada=verde, rechazada=rojo, cerrada=neutro). Moneda en COP.

---

## 9. Seeders / datos de prueba

`DatabaseSeeder` que genere:
- Las 2 filas de `request_types` con sus matrices (§4).
- Los 5 roles (§5).
- 3–4 `areas`.
- Los `per_diem_rates` sugeridos (valores de ejemplo por rubro).
- Usuarios demo, uno por cada rol (password conocido, ej. `password`), y alguno con varios roles, para probar el flujo completo end-to-end.
- Opcional: 1–2 solicitudes de ejemplo en distintos estados.

---

## 10. Convenciones y entregables

- Crea un **`CLAUDE.md`** en la raíz documentando: arquitectura del motor de solicitudes, el patrón Inertia (controlador → `Inertia::render` → página React con props), cómo agregar un nuevo tipo de proceso (insertar `request_type` + cabecera polimórfica + migración + matriz + página de creación), convenciones de nombres y comandos de arranque.
- **README** con: instalación, `.env` para MariaDB, `migrate --seed`, `npm install`, y los dos modos: `npm run dev` (desarrollo, con `php artisan serve`) y `npm run build` (producción). Incluye la **nota de despliegue**: compilar local/CI y subir `public/build`; no dejar `public/hot`.
- Dominio en español (estados, rubros, roles, rutas), infraestructura Laravel estándar en inglés, de forma consistente.
- Tests: al menos un test de feature del `WorkflowService` que recorra el flujo completo de cada proceso (creación → … → cierre) y verifique que una transición sin el rol correcto es rechazada. Opcional: un test Inertia con `assertInertia` sobre la página de detalle.

---

## 11. Orden de construcción (POR FASES — detente al final de cada una)

**Fase 0 — Andamiaje.** Crea el proyecto Laravel 11. Instala **Laravel Breeze con stack Inertia + React**, spatie/laravel-permission e Inertia. Configura `.env` para MariaDB y Tailwind. Verifica que `php artisan serve` + `npm run dev` levantan y que el login de Breeze funciona. **Detente y reporta el árbol de directorios y versiones (Laravel, PHP, Node).**

**Fase 1 — Datos.** Todas las migraciones (§3), enums de estados, seeders de roles/areas/request_types/rates/usuarios demo. Ejecuta `migrate:fresh --seed`. **Detente y reporta el esquema.**

**Fase 2 — Dominio.** Modelos Eloquent con relaciones (polimórfica `requests` ⇄ cabeceras, ítems, travelers, allocations, transitions), regla de oro de totales, generación de radicado, `WorkflowService`, Policy, excepción de dominio, notificaciones. Tests del servicio. **Detente y reporta.**

**Fase 3 — Web / controladores.** Rutas `web.php`, controladores Inertia, FormRequests, API Resources como props, middleware `HandleInertiaRequests` con props compartidas (§7.3). Verifica las rutas con el flujo completo (tests o navegación manual). **Detente y reporta.**

**Fase 4 — Páginas base.** `AppLayout`, página Index con bandejas, página Show con timeline y botonera dinámica, consumiendo props reales. **Detente y reporta.**

**Fase 5 — Páginas de procesos.** Formularios de creación (oficina y viáticos) con `useForm`, y el grid de liquidación de viáticos. Recorre el flujo completo end-to-end desde la UI. **Detente y reporta.**

---

**Empieza por la Fase 0.** Antes de escribir código, muéstrame el árbol de directorios que vas a crear y confirma las versiones exactas (Laravel, PHP, Node) y que usarás el starter de Breeze Inertia + React. Luego procede.
