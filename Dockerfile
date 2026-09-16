# Imagen para el ambiente de demo en Render. El despliegue de produccion en
# el VPS sigue siendo Nginx + PHP-FPM directo, sin Docker; este archivo no lo
# reemplaza ni lo afecta.
#
# El plan gratis de Render no tiene disco persistente: cualquier archivo que
# la aplicacion guarde en storage/app desaparece en el proximo restart o
# deploy. Hoy no se sube ningun archivo de usuario, pero va a importar cuando
# llegue el modulo educativo con archivos subidos, y va a haber que resolverlo
# con almacenamiento externo (S3 u otro).
#
# TODO doc: documentar esta limitacion en manual-tecnico.md junto al resto de
# lo pendiente para produccion, cuando llegue esa pasada de documentacion.

# --- Etapa 1: assets ---------------------------------------------------
# Misma version de Node que usa el CI (.github/workflows/tests.yml).
FROM node:22-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ resources/
COPY public/ public/
COPY vite.config.js tsconfig.json components.json ./

RUN npm run build

# --- Etapa 2: aplicacion -------------------------------------------------
# Misma version de PHP que usa el CI (.github/workflows/tests.yml).
FROM php:8.4-cli-bookworm AS app

WORKDIR /var/www/html

# xmlwriter (QR del segundo factor) y pdo_pgsql (PostgreSQL), tal como pide
# el README. libpq-dev y libxml2-dev son las cabeceras que necesitan para
# compilar; no hacen falta en tiempo de ejecucion pero dejarlas no rompe nada
# y evita una segunda etapa solo para separarlas.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install pdo_pgsql xmlwriter \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=assets /app/public/build public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Directorios que Laravel necesita poder escribir en tiempo de ejecucion
# (cache de vistas compiladas, sesiones, logs), con permisos para el usuario
# que corre `php artisan serve` mas abajo.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rwX storage bootstrap/cache

# /var/www (el HOME por defecto de www-data en esta imagen) es de root y no
# se le cambio el dueño mas arriba a proposito, para no aflojar permisos
# fuera de storage/ y bootstrap/cache/. `php artisan tinker` (via psysh)
# necesita un HOME donde escribir su config; /tmp ya es de todos.
ENV HOME=/tmp

USER www-data

EXPOSE 8080

# Sin Nginx ni supervisord: para el trafico de una demo, el servidor de
# desarrollo de Artisan alcanza y es menos superficie para que algo salga
# mal. Si mas adelante hace falta mas rendimiento, se cambia por FPM.
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
