#!/bin/bash
#
# cron-schedule-run.sh
#
# Wrapper que cron llama cada minuto en vez de correr `artisan schedule:run`
# directo. Antes que nada deja un timestamp en cron-heartbeat.log — eso
# prueba que el cron del sistema SÍ está disparando cada minuto, algo que
# storage/logs/scheduler.log no puede probar por sí solo (ese archivo recién
# se llena cuando algo del scheduler decide que le toca correr, no cada vez
# que cron invoca schedule:run — acá corren `delivery:sincronizar-todos` y
# `retiro:sincronizar-todos` cada 5 minutos, ver routes/console.php).
#
# Mismo patrón ya usado en `ApiRestEvent/cron-schedule-run.sh` — sin
# SSH/Terminal en esta cuenta (ver memoria `project_uat_deploy_access`),
# este es el único lugar donde queda registro de si el cron real del
# sistema operativo está funcionando o no.
#
# Reemplaza la línea del crontab (cPanel > Cron Jobs):
#
#   EN VEZ DE:
#   * * * * * /usr/local/bin/ea-php82 /home/inscrito/delivery.inscrito.net/artisan schedule:run >>/dev/null 2>&1
#
#   USAR:
#   * * * * * /bin/bash /home/inscrito/delivery.inscrito.net/cron-schedule-run.sh
#
# No hace falta `chmod +x` ni acceso a terminal para activarlo: al llamarlo
# como `/bin/bash script.sh` (en vez de `./script.sh`) alcanza con que el
# archivo exista y sea legible, que es lo que deja cualquier subida por FTP/
# File Manager.
#
# AJUSTAR APP_DIR abajo a la ruta real una vez creado el subdominio (ver
# MANUAL-INSTALACION-DELIVERY-CPANEL-01082026.md §2) — el valor de acá es
# una suposición basada en el patrón ya usado para `api.inscrito.net`.

APP_DIR="/home/inscrito/delivery.inscrito.net"
PHP_BIN="/usr/local/bin/ea-php82"

HEARTBEAT_LOG="$APP_DIR/storage/logs/cron-heartbeat.log"
CRON_RUN_LOG="$APP_DIR/storage/logs/cron-run.log"

# Prueba de que cron disparó el script — independiente de si Laravel tenía
# algo que hacer o no en este minuto puntual.
echo "$(date '+%Y-%m-%d %H:%M:%S') cron disparó el script" >> "$HEARTBEAT_LOG"

# stdout/stderr de schedule:run en sí (no de los comandos individuales, esos
# van a scheduler.log si se agrega ->appendOutputTo() en routes/console.php,
# ver nota abajo) — captura acá errores catastróficos de arranque de Laravel
# (ej. .env roto, autoload faltante) que antes se perdían en /dev/null.
"$PHP_BIN" "$APP_DIR/artisan" schedule:run >> "$CRON_RUN_LOG" 2>&1

# Nota: a diferencia de ApiRestEvent, routes/console.php de este proyecto
# todavía no manda el output de delivery:sincronizar-todos/
# retiro:sincronizar-todos a un log propio (scheduler.log) — hoy ese output
# solo se ve corriendo el comando a mano. Si hace falta auditar qué
# sincronizó el cron en cada corrida, agregar ->appendOutputTo(storage_path
# ('logs/scheduler.log')) a esos dos Schedule::command(...) en
# routes/console.php (mismo patrón que ya usa ApiRestEvent).
#
# Ninguno de estos 2 logs (cron-heartbeat.log, cron-run.log) rota
# automáticamente — van a crecer indefinidamente. cron-heartbeat.log suma
# ~1 línea/minuto (~500KB/año, no urgente). Si en algún momento se vuelve
# molesto, se puede truncar a mano por File Manager.
