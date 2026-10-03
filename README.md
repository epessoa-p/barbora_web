# Barbora — Panel web

SaaS multi-empresa para barberías. Cada barbería es un *tenant* aislado, con su
plan, su suscripción y sus propios datos.

## Requisitos

- PHP 8.2+
- MySQL 8
- Composer
- Node.js 20+ *(para compilar los estilos; sin él la app funciona igual,
  cargando Bootstrap desde el CDN — ver [Assets](#assets))*
- MySQL corriendo en el puerto que indique `DB_PORT` (en esta máquina, 3317)

## Instalación

```bash
composer install
cp .env.example .env          # ajusta DB_* y BARBORA_*
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build  # opcional, ver Assets
php artisan serve
```

## Credenciales de demostración

Todas con la contraseña `Admin@1234`.

| Usuario | Papel |
|---|---|
| `superadmin@barbora.test` | Operador de la plataforma. Sin empresa: arranca en **Modo Global** y ve todas. |
| `admin@barberiademo.test` | Administra Barbería Demo (plan Premium, activo). |
| `cajero@barberiademo.test` | Permisos recortados: sirve para ver el filtrado en acción. |
| `admin@barberiaprueba.test` | Administra Barbería Prueba (plan Básico en prueba, con un override de sucursales). |

Hay **dos** barberías sembradas a propósito: con una sola no se puede comprobar
a mano que el aislamiento funciona.

## Arquitectura

La referencia completa está en
[`../ARQUITECTURA-EMPRESAS-PLANES-PERMISOS.md`](../ARQUITECTURA-EMPRESAS-PLANES-PERMISOS.md).
En resumen, cada petición atraviesa cuatro capas en este orden:

```
¿La suscripción de la empresa está vigente?   → EnsureSubscriptionActive
    └─ ¿El plan incluye el módulo?            → CheckPlanModule       (plan:agenda, plan:pos…)
        └─ ¿El usuario tiene el permiso?      → CheckPermission       (branches.create…)
            └─ ¿Queda cupo en el plan?        → planLimitReached()    (solo al CREAR)
```

El **superadmin** (`users.is_super_admin`) salta las cuatro.

### Aislamiento entre empresas

`App\Support\Tenancy` guarda la empresa activa de la petición; el trait
`BelongsToCompany` + `CompanyScope` filtran e inyectan `company_id`
automáticamente en `Branch`, `Caja`, `Cargo` y `Personal`. El código de negocio
nunca escribe `where('company_id', …)`.

Tres estados, y la diferencia entre los dos últimos importa:

| Estado | Efecto |
|---|---|
| Con empresa | Filtra por ese `company_id`. |
| Sin empresa | **Fail-closed**: no devuelve nada. Nunca "todo". |
| Sin restricción | Modo Global del superadmin: sin filtro. |

Son modelos **globales** (nunca llevan el trait): `User`, `Company`, `Role`,
`Permission`, `Plan`, `Subscription`.

### Planes y suscripciones

Un plan define **módulos** (`Plan::MODULES`: agenda, pos, caja, inventario,
comisiones, clientes, estadisticas, reservas_online) y **límites**
(`max_users`, `max_branches`, `max_products`; `NULL` = ilimitado).

`Plan::PERMISSION_MODULE_FEATURES` decide qué módulo de permisos exige qué
módulo de plan. Lo que **no** figura ahí es administrativo y está siempre
disponible (empresas, usuarios, roles, cargos, personal, sucursales). Hoy la
única entrada es `cajas => caja`: la ruta lleva `plan:caja` y el menú oculta el
enlace, de modo que el guardia es real y no solo visual.

Cada empresa tiene **una** suscripción, cuyo estado se **deriva de las fechas**:
una vencida corta el acceso sola, sin que el operador toque nada. Dentro de
`grace_days` se puede consultar pero no registrar cambios.

Los **overrides** de la suscripción (`max_*_override`, `features_override`)
permiten ampliar o recortar el plan para una empresa concreta sin tocar el plan
global.

### Roles: quién es el dueño

`roles.company_id` decide quién manda sobre un rol:

| `company_id` | Qué es | Quién lo edita |
|---|---|---|
| `NULL` | Rol **del sistema**: catálogo del operador, compartido por todas las barberías (`admin`, `manager`, `cashier`, `barber`, `receptionist`, `super_admin`) | Sólo el superadmin, en Plataforma → Roles |
| `<id>` | Rol **propio** de esa barbería, creado desde su pantalla de cargos | Sólo esa barbería |

De ahí salen tres reglas que el código sostiene en un único sitio, los scopes de
`Role`:

- **Ver** (`availableFor`): los del sistema más los suyos.
- **Asignar** (`assignableBy`): lo anterior menos `super_admin`; si no, un
  administrador podría darse todos los permisos del SaaS.
- **Editar permisos** (`permissionsEditableBy`): sólo los suyos. Una barbería
  nunca escribe sobre un rol del sistema, porque lo comparte con las demás.

Además, los permisos de `Permission::PLATFORM_MODULES` (empresas, planes,
suscripciones, roles) son del operador y no se conceden desde un cargo.

`Role` es un modelo **global a propósito**: no lleva `CompanyScope`, porque el
scope escondería los roles del sistema (`company_id` nulo) a todas las empresas.
El filtrado va explícito en los scopes. Lo cubre `tests/Feature/RoleOwnershipTest.php`.

### API móvil (`/api/v1`)

Para el **personal** de la barbería (ver `../barbora_movil`), con Sanctum por
token. No hay sesión, así que la empresa activa viaja en cada petición:

```
Authorization: Bearer <token>
X-Company-Id:  <id de la barbería>
```

**`X-Company-Id` la manda el cliente, así que `SetTenant` la verifica contra las
empresas del usuario en cada petición.** Sin esa comprobación, cualquiera con un
token válido leería los datos de cualquier barbería cambiando un número; con
ella, responde `403 company_forbidden`. Lo cubre `ApiTest`.

Las cuatro capas de autorización son **las mismas** que en la web y corren en el
mismo orden. Lo único que cambia es la forma de decir que no, que vive en
`app/Http/Middleware/Concerns/RespondsToApi.php`:

| Situación | HTTP | `error` |
|---|---|---|
| Sin token o token revocado | 401 | `unauthenticated` |
| Empresa ajena en la cabecera | 403 | `company_forbidden` |
| Varias empresas y sin cabecera | 409 | `company_required` |
| Suscripción vencida | 402 | `subscription_blocked` / `subscription_readonly` |
| Módulo fuera del plan | 403 | `plan_required` |
| Sin permiso | 403 | `forbidden` |

Tres detalles que parecen menores y no lo son:

- **`SetTenant` corre antes que `auth:sanctum`**, así que resuelve el usuario con
  `auth('sanctum')->user()`, no con `auth()->user()` (que usa el guard `web` y
  devolvería `null`). En los tests con `Sanctum::actingAs()` esto no se nota
  porque cambia el guard por defecto: por eso hay pruebas con token Bearer real.
- **Los importes se serializan con `JSON_PRESERVE_ZERO_FRACTION`** (ver
  `Api\V1\ApiController`). Sin eso, PHP escribe `30` en vez de `30.0` y un
  cliente Dart que lea `as double` revienta solo con las cifras redondas.
- **Las horas llevan la zona de la barbería** (`App\Support\ShopTime`), porque el
  sistema guarda «hora de pared» y `config('app.timezone')` es UTC.

`GET /me` devuelve todo lo que la app necesita para arrancar: usuario, empresa,
ficha de personal, rol, permisos, módulos del plan y estado de la suscripción.
Junto con `login`, `logout` y `companies` está exento del corte por suscripción,
para que una barbería con la cuota vencida pueda al menos entrar y ver por qué.

Las rutas `my/*` (horario, agenda y comisiones propias) no llevan
`check-permission`: un barbero no necesita permiso sobre lo ajeno para ver lo
suyo. A cambio, `MyController` filtra siempre por su propia ficha de personal.

#### Reservar citas

Las reglas de reserva viven en **`App\Support\AppointmentBooker`**, y las usan la
agenda web y la API: no en el pasado, dentro de un turno del barbero, fuera de
bloqueos y sin pisar otra cita.

| Ruta | Permiso | Para qué |
|---|---|---|
| `GET /staff` | `appointments.create` | Personal que atiende citas (`bookable`) |
| `GET /booking/services` | `appointments.create` | Catálogo de servicios para reservar |
| `GET /booking/slots?personal_id&date&services[]` | `appointments.create` | Horas de inicio libres (`['09:00', …]`, cada 15 min) |
| `POST /appointments` | `appointments.create` | Crea la cita: `personal_id, client_id, date, time, services[], notes?` |
| `PUT /appointments/{id}` | `appointments.edit` | Reprograma (mismos campos). Una cita cerrada responde `422 appointment_closed` |
| `POST /clients` | `clients.create` | Alta rápida: `full_name, phone?` |

La ficha del cliente va aparte, en el módulo de clientes:

| Ruta | Permiso | Para qué |
|---|---|---|
| `GET /clients` | `clients.view` | Lista paginada y búsqueda (`?q=`) |
| `GET /clients/{id}` | `clients.view` | Ficha: datos + `stats` (visitas, gasto, última visita, citas por venir) + `recent_appointments` |

`stats` cuenta solo ventas **pagadas** como visitas (una comanda anulada no es una
visita). El binding `{client}` va acotado a la empresa activa, así que una ficha
de otra barbería responde 404. Lo cubre `ApiBookingTest`.

`/booking/services` existe aparte de `/services` porque recepción reserva sin
tener `services.view`. La promesa que cubre `ApiBookingTest`: **toda hora que
`slots` ofrece es una hora que `POST /appointments` acepta** — los dos salen de
las mismas reglas.

#### Cobrar sin cobrar dos veces

`POST /sales` acepta la cabecera **`Idempotency-Key`**, y conviene mandarla
siempre desde un móvil. El motivo: si el teléfono pierde la red justo después de
enviar el cobro y reintenta, sin la clave se crearían **dos ventas** — doble
descuento de stock, doble comisión y doble ingreso en caja.

| Caso | Qué pasa |
|---|---|
| Sin cabecera | Se cobra y ya está |
| Clave nueva | Se cobra y se guarda la respuesta |
| Clave repetida, mismo cuerpo | Devuelve la venta original con `idempotent_replay: true` |
| Clave repetida, otro cuerpo | `422 idempotency_key_reused` |

Un detalle que parece menor y no lo es: la respuesta guardada se conserva como
**texto**, no como array. Si se decodificara y volviera a codificar, `100.0` se
convertiría en `100` y el `as double` del cliente Dart reventaría — y sólo en el
reintento, es decir, justo después de un fallo de red. Ver
`App\Support\Idempotency` y su test en `ApiCashTest`.

### Métodos de pago

Cada barbería define los suyos (`payment_methods`): efectivo, tarjeta, Tigo
Money, el QR de su banco. Antes eran cuatro valores fijos en un `enum`.

La columna que sostiene el módulo es **`counts_as_cash`**. Solo lo que la lleva
entra en el arqueo de caja, porque es lo único que de verdad está en el cajón:
un cobro con tarjeta o por billetera está en el banco, y contarlo haría que el
turno cuadre mal todos los días. De ahí salen `CashSession::totals()` y
`Sale::cashAmount()`, que antes miraban el literal `'efectivo'`.

En `sale_payments` y `cash_movements` se guarda el **slug**, no el id: así
renombrar «QR» a «QR Banco Unión» no toca el historial, y un método dado de baja
sigue teniendo nombre en los arqueos donde se usó. Por eso un método que ya se
usó no se borra, se desactiva, y siempre tiene que quedar al menos uno marcado
como efectivo.

Toda barbería nace con los cuatro de siempre vía `CompanyObserver`, igual que
cada sucursal nace con su almacén. La app móvil los recibe en `GET /me`.

### Datos del comprobante

Cada barbería carga su logo, dirección, teléfono, correo y el pie del ticket en
Configuración → Datos de la barbería. **El nombre y el NIT no se editan ahí**:
son los datos con los que el operador verifica la identidad en soporte, y se
cambian desde el panel de plataforma.

El logo se guarda en `storage/app/public/companies/{id}/` y su URL se arma con
`asset()`, no con `Storage::url()`. La diferencia importa: `Storage::url()` usa
`APP_URL`, y si no coincide con el host real (otro puerto, o un móvil que entra
por la IP de la red) la imagen sale rota. `asset()` usa el host de la propia
petición, así que el panel y la app ven el logo cada uno por su camino.

### Agenda: horarios y bloqueos

Dos tablas distintas, que se leen juntas:

- **`work_schedules`** — lo recurrente: «Tania trabaja los martes de 09:00 a 13:00».
- **`agenda_blocks`** — lo excepcional: «Tania está de vacaciones del 1 al 15».

**El bloqueo manda sobre el horario.** Tener turno el martes no basta si ese
martes concreto está bloqueado. Con `personal_id` en `NULL` el bloqueo es de
toda la barbería, es decir, un feriado.

Al crear un bloqueo sobre citas ya reservadas se **avisa** de cuántas caen
dentro, pero no se cancelan: qué hacer con un cliente ya citado es una decisión
de la barbería, no del sistema. Lo que sí se impide es reservar encima.

Los bloqueos llevan permisos propios (`agenda_blocks.*`) en vez de reutilizar
`appointments.*`: recepción agenda todo el día pero no declara feriados.

### Reportes

Las consultas viven en `app/Support/Reports/` (una clase por reporte) y el rango
de fechas en `app/Support/ReportPeriod.php`. El controlador sólo arma el periodo
y pinta, para que el dashboard y la futura API móvil reutilicen las mismas
consultas sin copiarlas.

**El costo de los productos va congelado**, igual que el precio: `sale_items`
guarda `unit_price` y `unit_cost` tal como estaban el día de la venta, así que
subir el precio de compra de un producto no altera el margen de un mes ya
cerrado. `unit_cost` en `NULL` significa «no se sabe» —un servicio, o una venta
anterior a que se guardara— y en ese caso el reporte lo estima con el costo
actual y marca `estimated`, que es lo que hace aparecer el aviso en la vista.

Tres reglas al tocar este módulo:

- **Siempre a través de los modelos**, nunca `DB::table()`. Un reporte agrega
  muchas filas de golpe, así que si el `CompanyScope` no aplicara, el resultado
  sería un número mayor, no un error: se vería bien y estaría mal.
- **`DATE()` y poco más** en SQL crudo: es de lo poco que se comporta igual en
  MySQL y en SQLite, y los tests corren sobre SQLite.

La exportación a Excel es CSV con BOM UTF-8, separador `;` y coma decimal, que
es lo que abre bien de un doble clic con la configuración regional en español.
Para PDF, cada reporte trae vista de impresión (`@media print` en
`resources/scss/_reports.scss`); no se instaló ninguna librería de render.

## Assets

Los estilos son Bootstrap 5.3 compilado con Vite desde `resources/scss/`.
`resources/views/layouts/partials/assets.blade.php` detecta si existe el
manifest de Vite: si no lo hay (máquina sin Node, clon recién hecho) carga
Bootstrap desde el CDN para que la app siga funcionando. En cuanto ejecutes
`npm run build`, Vite toma el control solo.

## Tests

```bash
php artisan test
```

Cubren el aislamiento entre empresas, el bypass del superadmin, los cuatro
estados de suscripción, los límites de plan con sus overrides y el filtrado por
permisos.

## Deuda técnica conocida

- **Soft delete e índices únicos.** `users.email`, `companies.tax_id` y
  `plans.slug` son `unique` sin incluir `deleted_at`: un registro borrado
  bloquea ese valor para siempre. (`roles` ya usa `unique(company_id, slug)`,
  pero sigue sin contemplar `deleted_at`.)
- **Sin recuperación de contraseña.** No hay flujo de *reset*; sólo un
  administrador puede cambiarle la contraseña a un usuario.
- **Sin API todavía.** La app móvil (`../barbora_movil`) necesita
  `routes/api.php` + Sanctum + `X-Company-Id` + `GET /me`. El diseño de
  `Tenancy` y `SubscriptionGate` ya está preparado para ello.
