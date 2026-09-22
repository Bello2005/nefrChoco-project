# Despliegue — VPS de dirsoft.cloud

Cómo queda IPS NefroChocó corriendo en el mismo VPS (Hostinger, Ubuntu 24.04) donde ya vive `jitsi.bello.works`, y por qué se decidió así.

## Por qué nativo y no Docker

El servidor ya sirve más de diez sitios con nginx + PHP-FPM + PostgreSQL nativos; Docker solo se usa para el stack oficial de Jitsi (`docker-jitsi-meet`), porque así es como Jitsi se distribuye. Meter la app en un contenedor habría duplicado PHP-FPM y PostgreSQL sin necesidad, en una máquina de 2 vCPU / 8 GB compartida entre varios clientes. Un sitio nativo más, siguiendo el mismo patrón que ya usan `agrolink`, `granja`, `quantum`, `visitchoco`, etc., es lo que menos sorpresas trae.

## Qué hacen los scripts

| Script | Qué hace |
|---|---|
| [`deploy/firewall.sh`](../deploy/firewall.sh) | Activa `ufw` (estaba inactivo) dejando pasar solo 22 (SSH), 80/443 (todos los sitios) y 10000/udp (medios de Jitsi). Se corre aparte porque un firewall mal armado puede dejarte afuera del servidor. |
| [`deploy/deploy.sh`](../deploy/deploy.sh) | Instala dependencias del sistema, clona/actualiza el código, corre `composer`/`npm`, arma el `.env` de producción, migra la base de datos, cachea configuración y deja el `server {}` de nginx + certificado Let's Encrypt para `nefrochoco.bello.works`. Es idempotente: se puede volver a correr para desplegar una actualización y no toca un `.env` que ya exista. |

## Pasos, en orden

1. **DNS** (en el proveedor del dominio, no en el servidor): registro `A` de `nefrochoco.bello.works` → `72.61.0.99`. Sin esto, el paso de certificado del script falla.
2. En el servidor:
   ```bash
   git clone --branch main https://github.com/Bello2005/nefrChoco-project.git /var/www/nefrochoco-project
   cd /var/www/nefrochoco-project
   sudo bash deploy/firewall.sh   # confirmar SSH desde otra terminal antes de seguir
   sudo bash deploy/deploy.sh
   ```
3. El script imprime al final la contraseña del admin inicial (`admin@nefrochoco.co`). Se guarda una sola vez en `/root/.nefrochoco_admin_pass` — cámbiala al primer ingreso.

## Decisiones que quedaron tomadas

- **`JITSI_DOMAIN=jitsi.bello.works`** en vez de `meet.jit.si`: esto era justo el pendiente que ya tenía anotado el README ("Apuntar `JITSI_DOMAIN`... al Jitsi ya autoalojado").
- **Sin `--seed` en el primer `migrate`**: `EducationalContentSeeder` corre en todos los entornos, no solo `local`. Revisar el contenido con la médica de la IPS antes de correr `php artisan db:seed --force` a mano.
- **`MAIL_MAILER` sigue en `log`**: "olvidé mi contraseña" no envía correo todavía. Pendiente de credenciales SMTP reales (ver sección siguiente).
- **Contraseña de base de datos y del admin inicial**: se generan solas en el servidor (`openssl rand`) y se guardan en archivos con permisos `600` bajo `/root/`. Nunca viajan por chat ni se escriben en este repositorio.

## Pendiente: correo real (SMTP)

Para que el correo de recuperación de contraseña llegue de verdad hace falta un proveedor SMTP. Con [Brevo](https://www.brevo.com) (plan gratuito, 300 correos/día) alcanza para el volumen de esta plataforma:

1. Crear cuenta en Brevo con el correo institucional.
2. Verificar un remitente (el correo institucional, o mejor, un subdominio propio con los registros SPF/DKIM que Brevo indica).
3. En *Settings → SMTP & API → SMTP*, copiar la "SMTP key" (no es la contraseña de la cuenta).
4. En el servidor, editar `/var/www/nefrochoco-project/.env` y completar:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USERNAME=<correo con el que se creó la cuenta Brevo>
   MAIL_PASSWORD=<la SMTP key, no la contraseña de la cuenta>
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=notificaciones@nefrochoco.co
   MAIL_FROM_NAME="IPS NefroChocó"
   ```
5. `php artisan config:cache` para que tome el cambio.

Estas credenciales se escriben directo en el servidor, nunca se comparten por chat.
