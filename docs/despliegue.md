# Despliegue — VPS de pruebas

**Es un entorno de pruebas.** El VPS de pruebas (`nefrochoco.bello.works`) sirve para desarrollar y mostrar la plataforma con datos de demostración. La IPS montará su propio servidor de producción: los scripts de `deploy/` sirven de guía, pero la llave de respaldos, la Hora Legal y los datos reales se configuran allá.

Cómo queda IPS NefroChocó corriendo en el mismo VPS (Hostinger, Ubuntu 24.04) donde ya vive `jitsi.bello.works`, y por qué se decidió así.

**Datos de demostración** (solo en pruebas; crea cuentas con la contraseña `password`, no usar en producción): `sudo -u www-data php artisan db:seed --class=DemoDataSeeder --force`. Se corre una sola vez: repetirlo duplica citas e historias.

## Por qué nativo y no Docker

El servidor ya sirve más de diez sitios con nginx + PHP-FPM + PostgreSQL nativos; Docker solo se usa para el stack oficial de Jitsi (`docker-jitsi-meet`), porque así es como Jitsi se distribuye. Meter la app en un contenedor habría duplicado PHP-FPM y PostgreSQL sin necesidad, en una máquina de 2 vCPU / 8 GB compartida entre varios clientes. Un sitio nativo más, con el mismo patrón que los otros sitios del servidor, es lo que menos sorpresas trae.

## Qué hacen los scripts

| Script | Qué hace |
|---|---|
| [`deploy/firewall.sh`](../deploy/firewall.sh) | Activa `ufw` (estaba inactivo) dejando pasar solo 22 (SSH), 80/443 (todos los sitios) y 10000/udp (medios de Jitsi). Se corre aparte porque un firewall mal armado puede dejarte afuera del servidor. |
| [`deploy/deploy.sh`](../deploy/deploy.sh) | Instala dependencias del sistema, clona/actualiza el código, corre `composer`/`npm`, arma el `.env` de producción, migra la base de datos, cachea configuración y deja el `server {}` de nginx + certificado Let's Encrypt para `nefrochoco.bello.works`. Es idempotente: se puede volver a correr para desplegar una actualización y no toca un `.env` que ya exista. También configura la Hora Legal (INM) e instala los timers de respaldos (`nefrochoco-backup.timer`) y de tareas programadas (`nefrochoco-scheduler.timer`, que corre `schedule:run` cada minuto). |
| [`deploy/backup.sh`](../deploy/backup.sh) | Respaldo diario de la base, cifrado con la llave pública de la IPS. Lo corre el timer `nefrochoco-backup.timer`; ver "Respaldos cifrados". |

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

## Si el navegador dice "La conexión no es privada"

Prueba desde el servidor: `curl -svI https://nefrochoco.bello.works 2>&1 | grep subject`. Si el `subject` es **otro sitio** (el dominio de otro sitio alojado en el mismo servidor), nginx no tiene el bloque 443 de NefroChocó y está entregando el certificado de otro sitio del servidor. Pasaba en versiones viejas de `deploy.sh`: el paso 8 reescribía el sitio de nginx sin el 443 y el paso 9 no volvía a instalarlo. Arreglo inmediato, sin pedir certificado nuevo:

```bash
sudo certbot --nginx -d nefrochoco.bello.works --non-interactive --redirect --keep-until-expiring
```

El panel de Hostinger puede mostrar el SSL como "inactivo": ese panel no ve los certificados de Certbot dentro del VPS. Lo que vale es la prueba con `curl`.

## Si la página responde 500 y `laravel.log` no dice nada

Mira `tail /var/log/nginx/error.log`. Si dice *"Your Composer dependencies require a PHP version >= 8.4.1"*, nginx está usando el PHP-FPM equivocado. NefroChocó necesita PHP 8.4 (`PHP_VERSION=8.4` en `deploy.sh`); los otros sitios del servidor siguen en 8.3. Volver a correr `deploy.sh` rearma el sitio de nginx con el socket de 8.4.

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

## Respaldos cifrados (Res. 1644 de 2026, art. 14 par. 2)

`deploy/deploy.sh` instala el timer `nefrochoco-backup.timer`, que corre [`deploy/backup.sh`](../deploy/backup.sh) todos los días a las 3:30 a. m. (hora de Colombia). El script:

- vuelca la base con `pg_dump -Fc`, leyendo las credenciales del `.env` de la app sin imprimirlas;
- cifra el volcado con [`age`](https://age-encryption.org) **usando solo la llave pública de la IPS**. El servidor puede cifrar, pero no descifrar: quien se lleve el disco o los respaldos no puede leerlos;
- nunca escribe un volcado sin cifrar. Si falta la llave pública, se niega a correr;
- guarda los archivos en `/var/backups/nefrochoco/{daily,weekly,monthly}` con permisos `700`.

### Primera vez: la llave de la IPS

La llave privada **no se genera ni se guarda en el servidor**. En un computador de la IPS (idealmente sin conexión a internet):

```bash
age-keygen -o nefrochoco-respaldos.key      # llave privada: se guarda con la IPS, en dos lugares seguros
age-keygen -y nefrochoco-respaldos.key      # imprime la llave pública (empieza por age1...)
```

En el servidor se guarda solo la pública:

```bash
sudo install -m 600 /dev/null /etc/nefrochoco/backup-recipients.txt
sudo nano /etc/nefrochoco/backup-recipients.txt   # pegar la línea age1...
sudo systemctl start nefrochoco-backup.service      # primer respaldo de prueba
sudo journalctl -u nefrochoco-backup -n 20
```

Si la llave privada se pierde, **los respaldos no se pueden abrir nunca más**.

### Retención

Se configura en `/etc/nefrochoco/backup.env` (opcional):

```bash
KEEP_DAILY=7      # [CONFIRMAR con la IPS]
KEEP_WEEKLY=4     # [CONFIRMAR con la IPS] copia de cada domingo
KEEP_MONTHLY=12   # [CONFIRMAR con la IPS] copia del día 1 de cada mes
```

Esto es cuántas copias de recuperación se guardan en el servidor. La historia clínica en sí se conserva 15 años en la base (Res. 839 de 2017).

### ⚠️ La APP_KEY se respalda aparte

Los volcados contienen las columnas cifradas **tal como están en la base: cifradas con la `APP_KEY`**. Un respaldo sin su `APP_KEY` sirve para restaurar la estructura, pero la historia clínica, las notas, los teléfonos y los formularios quedan **ilegibles para siempre**.

- La `APP_KEY` (`grep '^APP_KEY=' /var/www/nefrochoco-project/.env`) se guarda en el gestor de contraseñas de la IPS, **separada** de los respaldos y de la llave de `age`.
- Si la `APP_KEY` cambia, los respaldos anteriores necesitan la llave vieja: guarda todas las versiones con su fecha.

### Restaurar un respaldo, paso a paso

En los comandos, `servidor` es tu acceso SSH al VPS (el alias o `usuario@IP` que uses).

El volcado se descifra en el computador que tiene la llave privada y viaja por SSH directo a `pg_restore`. **Nunca queda en claro en el disco de ninguno de los dos equipos.**

1. Copia el respaldo que vas a usar al computador de la IPS:
   ```bash
   scp servidor:/var/backups/nefrochoco/daily/nefrochoco-AAAA-MM-DD_HHMMSS.dump.age .
   ```
2. En el servidor, **respalda primero el estado actual** (si algo sale mal, vuelves a él) y pon la app en mantenimiento:
   ```bash
   sudo systemctl start nefrochoco-backup.service
   cd /var/www/nefrochoco-project && sudo -u www-data php artisan down
   ```
3. Desde el computador de la IPS, descifra y restaura en un solo paso:
   ```bash
   age -d -i nefrochoco-respaldos.key nefrochoco-AAAA-MM-DD_HHMMSS.dump.age \
     | ssh servidor 'sudo -u postgres pg_restore --clean --if-exists -d nefrochoco'
   ```
4. Comprueba que la `APP_KEY` del `.env` es la misma que había cuando se hizo ese respaldo, y levanta la app:
   ```bash
   cd /var/www/nefrochoco-project && sudo -u www-data php artisan up
   ```
5. Entra con una cuenta de prueba y abre una historia clínica: si se lee el texto, la `APP_KEY` es la correcta.

### Prueba de restauración mensual (evidencia)

Una vez al mes se restaura el respaldo más reciente en una **base temporal**, sin tocar la real, y se anota el resultado. Un respaldo que nunca se probó no es un respaldo.

```bash
# En el servidor
sudo -u postgres createdb nefrochoco_prueba_restauracion
# Desde el computador de la IPS
age -d -i nefrochoco-respaldos.key ULTIMO.dump.age \
  | ssh servidor 'sudo -u postgres pg_restore -d nefrochoco_prueba_restauracion'
# En el servidor: contar pacientes en las dos bases y comparar
sudo -u postgres psql -d nefrochoco_prueba_restauracion -Atc 'select count(*) from patients'
sudo -u postgres psql -d nefrochoco -Atc 'select count(*) from patients'
# Borrar la base temporal al terminar
sudo -u postgres dropdb nefrochoco_prueba_restauracion
```

| Fecha | Respaldo usado | Quién | Pacientes (respaldo / real) | Resultado | Observaciones |
|---|---|---|---|---|---|
| | | | | | |

### Copias fuera del servidor (pendiente de decisión de la IPS)

Hoy los respaldos **solo viven en el mismo VPS**: protegen contra errores y borrados, pero no contra la pérdida del servidor. No se configuró ninguna copia a una nube ni a un servicio externo, porque eso saca datos de salud a un tercero, y esa decisión le corresponde a la IPS (contrato, encargado del tratamiento según la Ley 1581 de 2012 y ubicación de los datos).

Cuando la IPS lo decida, las opciones, de menos a más dependencia de terceros:

1. **Un equipo de la IPS** que descargue los `.age` todos los días con `rsync` por SSH desde el servidor (un usuario de solo lectura sobre `/var/backups/nefrochoco`).
2. **Un disco o NAS de la IPS** con la misma sincronización.
3. **Un almacenamiento en la nube** contratado por la IPS (por ejemplo, con `rclone`). Como los archivos ya salen cifrados con la llave pública, el proveedor no puede leerlos, pero igual requiere la decisión y el contrato de la IPS.

## Hora Legal de Colombia (Res. 1644 de 2026, art. 22)

`deploy/deploy.sh` configura `systemd-timesyncd` para sincronizar el reloj del servidor con los servidores NTP del Instituto Nacional de Metrología (`ntp1.inm.gov.co` y `ntp2.inm.gov.co`), que distribuyen la Hora Legal de Colombia. Afecta a todo el VPS, no solo a esta app. Si el servidor usa `chrony`, el script no lo toca y solo avisa qué agregar.

Cada despliegue guarda la evidencia en `/var/log/nefrochoco/hora-legal-<fecha>.txt` (salida de `timedatectl status` y `timedatectl timesync-status`). Para revisarla:

```bash
ls -1 /var/log/nefrochoco/hora-legal-*.txt | tail -1 | xargs cat
timedatectl timesync-status     # "Server:" debe mostrar un servidor del INM
```
