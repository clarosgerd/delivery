# elascenso/delivery

Servicio Laravel independiente para operar la **entrega de kits** de los
eventos de [Pass2Go](../event) — envíos a domicilio con repartidores y
tracking GPS en vivo, más una pantalla POS para retiro en sitio el día del
evento. Repo y base de datos propios; no comparte código con `elascenso/event`
ni con `ApiRestEvent`, y no requiere ningún cambio en ninguno de los dos.

Detalle completo del diseño y las decisiones en
`elascenso/event/brain/PLAN-SISTEMA-DELIVERY-STANDALONE-01082026.md`.

## Qué hace

- **Importa eventos** desde `ApiRestEvent` vía un link firmado
  (`delivery.dashboard.json`, generado con
  `php artisan delivery:generar-link {evento}` del lado de `ApiRestEvent`,
  no expira) — un solo pegado manual por evento, después se sincroniza solo.
- **Envíos a domicilio**: asigna repartidor, sigue el estado
  (pendiente → confirmado → entregado/cancelado) y reporta cada cambio de
  vuelta a `ApiRestEvent` (push-back vía el link firmado individual que ya
  trae cada participante) — sin necesidad de ningún endpoint nuevo del lado
  de `ApiRestEvent`.
- **Repartidores**: acceso por token opaco (`/repartidor/{token}`, sin
  login), tracking GPS en vivo (`navigator.geolocation.watchPosition()`) y
  mapa de administración en `/mapa` (Leaflet + OpenStreetMap).
- **POS de retiro en sitio**: pantalla de mostrador (`/pos`) para cuando el
  participante retira su kit en persona el día del evento — cubre a todos
  los pagados, no solo a quienes pidieron delivery.
- **Sincronización automática**: `Schedule::command(...)->everyFiveMinutes()`
  (ver `routes/console.php`), más un botón "Sincronizar ahora" para no
  esperar.

## Requisitos

- PHP ^8.2, Laravel 12
- MySQL/MariaDB (base `elascenso_delivery`)
- Sin Node/npm: la UI usa Tailwind vía CDN, no hay build step

## Instalación local

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Editar `.env`: `DB_DATABASE=elascenso_delivery`, `DB_USERNAME`/`DB_PASSWORD`
según tu MySQL local.

```bash
php artisan migrate --seed
php artisan serve --port=8010
```

Login de admin de desarrollo: `gerdclaros@gmail.com` / `delivery123`
(cambiar antes de cualquier ambiente real).

Para que la sincronización automática corra en local:

```bash
php artisan schedule:work
```

(en producción, un cron del sistema operativo dispara `schedule:run` cada
minuto — ver `cron-schedule-run.sh` y
`elascenso/event/brain/MANUAL-INSTALACION-DELIVERY-CPANEL-01082026.md`).

## Estructura relevante

```
app/Models/          EnvioDelivery, Repartidor, UbicacionRepartidor,
                      RetiroSitio, EventoDeliveryConfig, EventoRetiroConfig
app/Services/         DeliverySyncService, RetiroSyncService
app/Console/Commands/ SincronizarDelivery, SincronizarRetiro
                      (delivery:sincronizar-todos / retiro:sincronizar-todos)
resources/views/      Blade + Tailwind CDN, componentes en components/
```

## Pruebas

Checklist de QA visual (login, dashboard, repartidor en celular, mapa, POS
en tablet) en `elascenso/event/brain/TEST_PLAN_DELIVERY_VISUAL.md`.
