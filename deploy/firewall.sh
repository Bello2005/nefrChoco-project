#!/usr/bin/env bash
# Activa ufw en el VPS de pruebas sin cortar el acceso SSH.
# Deja pasar exactamente lo que ya estaba en uso al analizar el servidor:
# 22 (SSH), 80/443 (todos los sitios detrás de nginx) y 10000/udp (Jitsi).
#
# Uso: sudo bash deploy/firewall.sh
#
# Después de correrlo, prueba el SSH desde una terminal NUEVA (sin cerrar
# esta) antes de seguir con cualquier otra cosa. Si algo sale mal, entras
# por la consola de Hostinger y corres: ufw disable

set -euo pipefail

echo "Reglas actuales antes de tocar nada:"
ufw status verbose || true

ufw allow OpenSSH
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 10000/udp

ufw --force enable

echo ""
echo "Firewall activo. Reglas actuales:"
ufw status verbose

echo ""
echo "AHORA: abre una terminal NUEVA y confirma que tu acceso por ssh todavía"
echo "funciona antes de continuar con el despliegue."
