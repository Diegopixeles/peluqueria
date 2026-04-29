#!/bin/bash
set -e

# ─────────────────────────────────────────────────────────────
# PASO 1: Ajustar el puerto de Apache al que Railway asigne
# ─────────────────────────────────────────────────────────────
APACHE_PORT="${PORT:-80}"
echo "[entrypoint] Configurando Apache en el puerto ${APACHE_PORT}..."
sed -i "s/Listen 80/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf


# ─────────────────────────────────────────────────────────────
# PASO 2: Init de BD en SEGUNDO PLANO (no bloquea Apache)
# Apache arranca de inmediato; la BD se importa cuando esté lista
# ─────────────────────────────────────────────────────────────
(
    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_NAME="${DB_NAME:-railway}"
    DB_USER="${DB_USER:-root}"
    DB_PASS="${DB_PASS:-}"

    echo "[db-init] Esperando MySQL en ${DB_HOST}..."

    MAX_RETRIES=40
    COUNT=0
    until mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SELECT 1;" > /dev/null 2>&1; do
        COUNT=$((COUNT + 1))
        if [ "${COUNT}" -ge "${MAX_RETRIES}" ]; then
            echo "[db-init] ⚠️  MySQL no disponible tras ${MAX_RETRIES} intentos. Saltando init."
            exit 0
        fi
        echo "[db-init] MySQL no listo... (intento ${COUNT}/${MAX_RETRIES})"
        sleep 5
    done

    echo "[db-init] ✅ MySQL conectado."

    # Importar esquema solo si la BD está vacía
    TABLE_COUNT=$(mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
        --skip-column-names 2>/dev/null || echo "0")

    if [ "${TABLE_COUNT}" -eq 0 ]; then
        echo "[db-init] 🗄️  Importando esquema SQL..."
        mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
            < /var/www/html/docker/mysql/init/railway_init.sql \
            && echo "[db-init] ✅ Esquema importado." \
            || echo "[db-init] ❌ Error al importar."
    else
        echo "[db-init] ✅ BD ya tiene ${TABLE_COUNT} tabla(s). Saltando importación."
    fi
) &


# ─────────────────────────────────────────────────────────────
# PASO 3: Arrancar Apache INMEDIATAMENTE (sin esperar a MySQL)
# ─────────────────────────────────────────────────────────────
echo "[entrypoint] 🚀 Arrancando Apache..."
exec apache2-foreground
