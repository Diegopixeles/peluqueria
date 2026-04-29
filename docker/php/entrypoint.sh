#!/bin/bash
set -e

# ─────────────────────────────────────────────────────────────
# PASO 1: Ajustar el puerto de Apache al que Railway asigne
# Railway inyecta la variable $PORT (por defecto 80 en local)
# ─────────────────────────────────────────────────────────────
APACHE_PORT="${PORT:-80}"

echo "[entrypoint] Configurando Apache en el puerto ${APACHE_PORT}..."

# Reemplaza el puerto en la configuración de Apache
sed -i "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf


# ─────────────────────────────────────────────────────────────
# PASO 2: Esperar a que MySQL esté disponible
# ─────────────────────────────────────────────────────────────
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-peluqueria}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo "[entrypoint] Esperando conexión a MySQL en ${DB_HOST}..."

MAX_RETRIES=30
COUNT=0
until mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SELECT 1;" > /dev/null 2>&1; do
    COUNT=$((COUNT + 1))
    if [ "${COUNT}" -ge "${MAX_RETRIES}" ]; then
        echo "[entrypoint] ⚠️  MySQL no disponible tras ${MAX_RETRIES} intentos. Arrancando Apache igualmente..."
        break
    fi
    echo "[entrypoint] MySQL no listo todavía... (intento ${COUNT}/${MAX_RETRIES})"
    sleep 3
done


# ─────────────────────────────────────────────────────────────
# PASO 3: Importar el esquema SQL si la BD está vacía
# Solo se ejecuta la primera vez (cuando no hay tablas)
# ─────────────────────────────────────────────────────────────
TABLE_COUNT=$(mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
    --skip-column-names 2>/dev/null || echo "0")

if [ "${TABLE_COUNT}" -eq 0 ]; then
    echo "[entrypoint] 🗄️  Base de datos vacía. Importando esquema inicial..."
    mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < /var/www/html/docker/mysql/init/railway_init.sql \
        && echo "[entrypoint] ✅ Esquema importado correctamente." \
        || echo "[entrypoint] ❌ Error al importar el esquema."
else
    echo "[entrypoint] ✅ La base de datos ya tiene ${TABLE_COUNT} tabla(s). Saltando importación."
fi


# ─────────────────────────────────────────────────────────────
# PASO 4: Arrancar Apache en primer plano
# ─────────────────────────────────────────────────────────────
echo "[entrypoint] 🚀 Arrancando Apache..."
exec apache2-foreground
