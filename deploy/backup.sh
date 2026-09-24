#!/usr/bin/env bash
# Respaldo cifrado de la base de datos de IPS NefroChocó.
#
# Res. 1644 de 2026, art. 14 par. 2: mecanismos de respaldo y recuperación ante
# fallas. Lo corre a diario el timer nefrochoco-backup.timer (lo instala
# deploy/deploy.sh); también se puede correr a mano: sudo bash deploy/backup.sh
#
# Por qué así:
# - Cifrado con `age` usando SOLO la llave pública. La privada vive fuera del
#   servidor, con la IPS: si alguien se lleva el disco o los respaldos, no puede
#   leerlos, y este servidor tampoco puede descifrarlos.
# - Nunca se escribe un volcado sin cifrar: pg_dump va por tubería directo a
#   age. Si falta la llave pública, el script se niega a correr.
# - Las credenciales se leen del .env de la app sin imprimirlas y sin hacer
#   `source` del .env (ejecutaría cualquier cosa que alguien meta ahí).
# - No copia nada a ninguna nube: eso saca datos de salud a un tercero. Ver
#   docs/despliegue.md, "Copias fuera del servidor".
#
# Configuración opcional en /etc/nefrochoco/backup.env (variables de abajo).

set -euo pipefail
umask 077

APP_DIR="${APP_DIR:-/var/www/nefrochoco-project}"
CONFIG_FILE="${CONFIG_FILE:-/etc/nefrochoco/backup.env}"

if [ -f "$CONFIG_FILE" ]; then
  # shellcheck disable=SC1090
  . "$CONFIG_FILE"
fi

BACKUP_DIR="${BACKUP_DIR:-/var/backups/nefrochoco}"
AGE_RECIPIENTS_FILE="${AGE_RECIPIENTS_FILE:-/etc/nefrochoco/backup-recipients.txt}"

# Cuántos respaldos se guardan de cada tipo. [CONFIRMAR con la IPS]: dependen
# del espacio en disco y de la política de la IPS. La historia clínica en sí se
# conserva 15 años en la base (Res. 839 de 2017); esto es solo cuántas copias
# de recuperación se guardan en el servidor.
KEEP_DAILY="${KEEP_DAILY:-7}"     # [CONFIRMAR con la IPS]
KEEP_WEEKLY="${KEEP_WEEKLY:-4}"   # [CONFIRMAR con la IPS]
KEEP_MONTHLY="${KEEP_MONTHLY:-12}" # [CONFIRMAR con la IPS]

log() { echo "[nefrochoco-backup] $*"; }
fail() { echo "[nefrochoco-backup] ERROR: $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "corre esto como root."
command -v age >/dev/null || fail "falta 'age' (apt-get install age)."
command -v pg_dump >/dev/null || fail "falta pg_dump."
[ -f "$APP_DIR/.env" ] || fail "no existe $APP_DIR/.env."

if [ ! -s "$AGE_RECIPIENTS_FILE" ] || ! grep -q '^age1' "$AGE_RECIPIENTS_FILE"; then
  fail "falta la llave pública en $AGE_RECIPIENTS_FILE (una línea que empieza por age1). Ver docs/despliegue.md. No se escribe ningún respaldo sin cifrar."
fi

# Un solo respaldo a la vez: si el anterior sigue corriendo, este no pisa.
exec 9>/run/nefrochoco-backup.lock
flock -n 9 || fail "ya hay un respaldo en curso."

# Lee una variable del .env sin ejecutarlo. Quita comillas envolventes.
env_value() {
  local value
  value=$(grep -E "^$1=" "$APP_DIR/.env" | tail -n 1 | cut -d= -f2-)
  value="${value%\"}"; value="${value#\"}"
  value="${value%\'}"; value="${value#\'}"
  printf '%s' "$value"
}

DB_HOST=$(env_value DB_HOST)
DB_PORT=$(env_value DB_PORT)
DB_DATABASE=$(env_value DB_DATABASE)
DB_USERNAME=$(env_value DB_USERNAME)
DB_PASSWORD=$(env_value DB_PASSWORD)

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
  fail "el .env no tiene DB_DATABASE o DB_USERNAME."
fi

# La contraseña va en un .pgpass temporal (600) y no en la línea de comandos ni
# en una variable de entorno, que otros procesos podrían ver.
PGPASSFILE=$(mktemp)
export PGPASSFILE
TMP_OUT=""
cleanup() {
  rm -f "$PGPASSFILE"
  [ -n "$TMP_OUT" ] && rm -f "$TMP_OUT"
}
trap cleanup EXIT
# pgpass escapa ':' y '\' con barra invertida.
escape_pgpass() { printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/:/\\:/g'; }
printf '%s:%s:%s:%s:%s\n' \
  "$(escape_pgpass "${DB_HOST:-127.0.0.1}")" "${DB_PORT:-5432}" \
  "$(escape_pgpass "$DB_DATABASE")" "$(escape_pgpass "$DB_USERNAME")" \
  "$(escape_pgpass "$DB_PASSWORD")" > "$PGPASSFILE"

mkdir -p "$BACKUP_DIR"/{daily,weekly,monthly}
chmod 700 "$BACKUP_DIR"

STAMP=$(date +%Y-%m-%d_%H%M%S)
NAME="nefrochoco-${STAMP}.dump.age"
TMP_OUT="$BACKUP_DIR/daily/.${NAME}.partial"

log "volcando ${DB_DATABASE} y cifrando..."
pg_dump -Fc --no-password -h "${DB_HOST:-127.0.0.1}" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" "$DB_DATABASE" \
  | age -R "$AGE_RECIPIENTS_FILE" -o "$TMP_OUT"

[ -s "$TMP_OUT" ] || fail "el respaldo quedó vacío."
mv "$TMP_OUT" "$BACKUP_DIR/daily/$NAME"
TMP_OUT=""
log "listo: $BACKUP_DIR/daily/$NAME ($(du -h "$BACKUP_DIR/daily/$NAME" | cut -f1))"

# Domingo: copia semanal. Día 1: copia mensual. Son copias duras (mismo archivo
# en disco), así que no ocupan espacio extra mientras la diaria exista.
if [ "$(date +%u)" = "7" ]; then
  ln -f "$BACKUP_DIR/daily/$NAME" "$BACKUP_DIR/weekly/$NAME"
fi
if [ "$(date +%d)" = "01" ]; then
  ln -f "$BACKUP_DIR/daily/$NAME" "$BACKUP_DIR/monthly/$NAME"
fi

# Deja solo los N más recientes de cada tipo.
prune() {
  local dir="$1" keep="$2"
  find "$dir" -maxdepth 1 -type f -name 'nefrochoco-*.dump.age' -printf '%f\n' \
    | sort -r | tail -n +"$((keep + 1))" \
    | while read -r old; do rm -f "$dir/$old"; log "retención: borrado $dir/$old"; done
}
prune "$BACKUP_DIR/daily" "$KEEP_DAILY"
prune "$BACKUP_DIR/weekly" "$KEEP_WEEKLY"
prune "$BACKUP_DIR/monthly" "$KEEP_MONTHLY"
