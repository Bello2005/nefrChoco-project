#!/usr/bin/env bash
# Despliega IPS NefroChocó en el VPS de dirsoft.cloud, nativo
# (nginx + PHP-FPM + PostgreSQL), igual que el resto de sitios del servidor.
# Docker se deja intacto: solo lo usa Jitsi, y este script no lo toca.
#
# Requisitos antes de correrlo:
#   1. Haber creado el registro DNS: A  nefrochoco.bello.works -> 72.61.0.99
#   2. Haber clonado el repo en /var/www/nefrochoco-project:
#        git clone --branch main https://github.com/Bello2005/nefrChoco-project.git /var/www/nefrochoco-project
#   3. (Recomendado) Haber corrido deploy/firewall.sh primero
#
# Uso: cd /var/www/nefrochoco-project && sudo bash deploy/deploy.sh
#
# Se puede volver a correr para actualizar (hace git reset --hard origin/main,
# reinstala dependencias y reconstruye). El .env NO se toca si ya existe,
# así que la configuración y las contraseñas no se pierden entre corridas.

set -euo pipefail

APP_DIR=/var/www/nefrochoco-project
DOMAIN=nefrochoco.bello.works
DB_NAME=nefrochoco
DB_USER=nefrochoco
PHP_VERSION=8.3

if [ "$(id -u)" -ne 0 ]; then
  echo "Corre esto como root (sudo bash deploy/deploy.sh)." >&2
  exit 1
fi

cd "$APP_DIR"

echo "== 1/12 Paquetes del sistema =="
apt-get update -y
apt-get install -y \
  php${PHP_VERSION}-xml php${PHP_VERSION}-pgsql php${PHP_VERSION}-mbstring \
  php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-gd \
  php${PHP_VERSION}-bcmath php${PHP_VERSION}-intl unzip git age
systemctl reload php${PHP_VERSION}-fpm

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
composer --version

NODE_MAJOR=0
if command -v node >/dev/null 2>&1; then
  NODE_MAJOR=$(node -v | sed 's/^v//; s/\..*//')
fi
if [ "$NODE_MAJOR" -lt 20 ]; then
  echo "Node del sistema es v${NODE_MAJOR} (se necesita >=20). Instalo nvm"
  echo "solo para esta sesión, sin tocar el node global que usan los otros sitios."
  curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
  export NVM_DIR="$HOME/.nvm"
  # shellcheck disable=SC1091
  . "$NVM_DIR/nvm.sh"
  nvm install 20
  nvm use 20
fi
node -v

echo "== 2/12 Base de datos =="
DB_PASS_FILE=/root/.nefrochoco_db_pass
if [ ! -f "$DB_PASS_FILE" ]; then
  DB_PASS=$(openssl rand -hex 24)
  echo -n "$DB_PASS" > "$DB_PASS_FILE"
  chmod 600 "$DB_PASS_FILE"
  sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}';"
  sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
else
  DB_PASS=$(cat "$DB_PASS_FILE")
  echo "Ya existía una contraseña de BD guardada en ${DB_PASS_FILE}, la reuso."
fi

echo "== 3/12 Código al día =="
git fetch origin main
git checkout main
git reset --hard origin/main

echo "== 4/12 Dependencias y build =="
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

echo "== 5/12 Configuración (.env) =="
if [ ! -f .env ]; then
  cp .env.example .env

  ADMIN_PASS_FILE=/root/.nefrochoco_admin_pass
  ADMIN_PASS=$(openssl rand -base64 18)
  echo -n "$ADMIN_PASS" > "$ADMIN_PASS_FILE"
  chmod 600 "$ADMIN_PASS_FILE"

  sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
  sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
  sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
  sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
  sed -i "s|^ADMIN_INITIAL_PASSWORD=.*|ADMIN_INITIAL_PASSWORD=${ADMIN_PASS}|" .env
  sed -i "s|^JITSI_DOMAIN=.*|JITSI_DOMAIN=jitsi.bello.works|" .env

  php artisan key:generate --force
  echo ".env creado. Contraseña del admin inicial guardada en ${ADMIN_PASS_FILE}."
else
  echo ".env ya existía, no lo toco (para no pisar configuración ya hecha a mano)."
fi

echo "== 6/12 Migraciones =="
php artisan migrate --force
echo "AVISO: no corrí 'migrate --seed'. EducationalContentSeeder corre en todos"
echo "los entornos (no solo local) — revisa su contenido con la médica antes de"
echo "correrlo a mano en producción: php artisan db:seed --force"

echo "== 7/12 Cache y permisos =="
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

PHP_FPM_USER=$(grep -oP '^user\s*=\s*\K.+' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf)
chown -R "${PHP_FPM_USER}:${PHP_FPM_USER}" storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "== 8/12 Nginx =="
PHP_SOCK=$(grep -oP '^listen\s*=\s*\K.+' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf)

cat > "/etc/nginx/sites-available/${DOMAIN}" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;

    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
NGINX

ln -sf "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
nginx -t
systemctl reload nginx

echo "== 9/12 Certificado SSL =="
# Siempre se corre: el paso 8 reescribe el sitio de nginx solo con el puerto
# 80, así que hay que volver a instalar el bloque 443. Antes se saltaba si el
# certificado existía y nginx quedaba sirviendo el certificado de otro sitio
# del servidor (el navegador lo rechaza). --keep-until-expiring reusa el
# certificado vigente: no pide uno nuevo en cada despliegue.
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos \
  --register-unsafely-without-email --redirect --keep-until-expiring

echo "== 10/12 Hora Legal de Colombia =="
# Res. 1644 de 2026, art. 22: los registros clínicos cronológicos deben poder
# demostrar sincronización con la Hora Legal de Colombia, que distribuye el
# Instituto Nacional de Metrología (INM) por NTP. Afecta el reloj de todo el
# servidor, no solo a esta app. No se cambia la zona horaria del sistema: la
# app ya usa APP_TIMEZONE=America/Bogota y NTP trabaja en UTC.
LOG_DIR=/var/log/nefrochoco
mkdir -p "$LOG_DIR"
if systemctl is-active --quiet chrony 2>/dev/null; then
  echo "AVISO: chrony está activo y maneja el reloj; no toco timesyncd."
  echo "Agrega a chrony: server ntp1.inm.gov.co iburst / server ntp2.inm.gov.co iburst"
else
  apt-get install -y systemd-timesyncd
  mkdir -p /etc/systemd/timesyncd.conf.d
  cat > /etc/systemd/timesyncd.conf.d/nefrochoco-hora-legal.conf <<'TIMESYNC'
# Hora Legal de Colombia (INM). Lo escribe deploy/deploy.sh.
[Time]
NTP=ntp1.inm.gov.co ntp2.inm.gov.co
TIMESYNC
  timedatectl set-ntp true
  systemctl restart systemd-timesyncd
  # Unos segundos para que alcance a consultar el servidor antes de guardar
  # la evidencia.
  sleep 10
  EVIDENCE="$LOG_DIR/hora-legal-$(date +%Y-%m-%d_%H%M%S).txt"
  {
    echo "Evidencia de sincronización con la Hora Legal (INM)"
    echo "Generada: $(date --iso-8601=seconds)"
    echo
    timedatectl status
    echo
    timedatectl timesync-status
  } > "$EVIDENCE" 2>&1 || true
  echo "Evidencia guardada en $EVIDENCE"
  grep -E "Server:" "$EVIDENCE" || echo "AVISO: todavía no aparece el servidor NTP; revisa $EVIDENCE"
fi

echo "== 11/12 Respaldos diarios cifrados =="
# Res. 1644 de 2026, art. 14 par. 2. El timer se instala siempre; backup.sh se
# niega a correr mientras no exista la llave pública de la IPS, así nunca
# queda un volcado sin cifrar.
mkdir -p /etc/nefrochoco
chmod 700 /etc/nefrochoco
cat > /etc/systemd/system/nefrochoco-backup.service <<UNIT
[Unit]
Description=Respaldo cifrado de la base de IPS NefroChocó
After=postgresql.service

[Service]
Type=oneshot
ExecStart=/bin/bash ${APP_DIR}/deploy/backup.sh
UNIT
cat > /etc/systemd/system/nefrochoco-backup.timer <<'UNIT'
[Unit]
Description=Respaldo diario de IPS NefroChocó

[Timer]
# 3:30 a. m. hora de Colombia, fuera del horario de atención.
OnCalendar=*-*-* 03:30:00 America/Bogota
RandomizedDelaySec=10min
# Si el servidor estaba apagado a esa hora, corre al encender.
Persistent=true

[Install]
WantedBy=timers.target
UNIT
systemctl daemon-reload
systemctl enable --now nefrochoco-backup.timer
if [ ! -s /etc/nefrochoco/backup-recipients.txt ]; then
  echo "AVISO: falta /etc/nefrochoco/backup-recipients.txt (llave pública age de la IPS)."
  echo "Hasta que exista, el respaldo diario falla a propósito. Ver docs/despliegue.md."
fi

echo "== 12/12 Tareas programadas de Laravel =="
# Recordatorios "Te toca medirte" (seguimiento:recordatorios) y cualquier otra
# tarea de routes/console.php. Sin cron: un timer de systemd que corre
# schedule:run cada minuto, igual de idempotente que el de respaldos.
PHP_FPM_USER_SCHED=$(grep -oP '^user\s*=\s*\K.+' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf)
cat > /etc/systemd/system/nefrochoco-scheduler.service <<UNIT
[Unit]
Description=Tareas programadas de IPS NefroChocó (schedule:run)

[Service]
Type=oneshot
User=${PHP_FPM_USER_SCHED}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan schedule:run
UNIT
cat > /etc/systemd/system/nefrochoco-scheduler.timer <<'UNIT'
[Unit]
Description=Cada minuto: schedule:run de IPS NefroChocó

[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true

[Install]
WantedBy=timers.target
UNIT
systemctl daemon-reload
systemctl enable --now nefrochoco-scheduler.timer

echo ""
echo "====================================================="
echo "LISTO: https://${DOMAIN}"
echo ""
echo "Contraseña del admin inicial (guárdala y cámbiala al entrar):"
cat /root/.nefrochoco_admin_pass 2>/dev/null || echo "(ya existía de antes, revisa /root/.nefrochoco_admin_pass)"
echo ""
echo "Pendiente que sigue quedando: MAIL_MAILER real (hoy 'log', el correo de"
echo "recuperación de contraseña no llega a nadie todavía)."
echo "====================================================="
