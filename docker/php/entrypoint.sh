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
# ─────────────────────────────────────────────────────────────
(
    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-3306}"
    DB_NAME="${DB_NAME:-railway}"
    DB_USER="${DB_USER:-root}"
    DB_PASS="${DB_PASS:-}"

    echo "[db-init] Esperando MySQL en ${DB_HOST}:${DB_PORT}..."

    # Usar netcat para comprobar que el puerto está abierto (más fiable que mysql)
    MAX_RETRIES=40
    COUNT=0
    until nc -z "${DB_HOST}" "${DB_PORT}" 2>/dev/null; do
        COUNT=$((COUNT + 1))
        if [ "${COUNT}" -ge "${MAX_RETRIES}" ]; then
            echo "[db-init] ⚠️  MySQL no disponible. Saltando init de BD."
            exit 0
        fi
        echo "[db-init] Puerto MySQL no abierto... (intento ${COUNT}/${MAX_RETRIES})"
        sleep 5
    done

    echo "[db-init] ✅ Puerto MySQL accesible. Esperando que el servidor esté listo..."
    sleep 5

    # Importar esquema solo si la BD está vacía
    TABLE_COUNT=$(mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
        --skip-column-names 2>/dev/null || echo "0")

    if [ "${TABLE_COUNT}" -eq 0 ]; then
        echo "[db-init] 🗄️  Importando esquema SQL..."
        mysql -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
            < /var/www/html/docker/mysql/init/railway_init.sql \
            && echo "[db-init] ✅ Esquema importado correctamente." \
            || echo "[db-init] ❌ Error al importar el esquema."
    else
        echo "[db-init] ✅ BD ya tiene ${TABLE_COUNT} tabla(s). Saltando importación."
    fi
) &


# ─────────────────────────────────────────────────────────────
# PASO 3: Arrancar Apache INMEDIATAMENTE
# ─────────────────────────────────────────────────────────────
echo "[entrypoint] 🚀 Arrancando Apache..."
exec apache2-foreground
