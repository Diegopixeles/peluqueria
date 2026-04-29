FROM php:8.2-apache

# ─────────────────────────────────────────────────────────────
# Instalar dependencias
# --no-install-recommends evita paquetes extra que pueden
# activar módulos de Apache adicionales (causa del MPM conflict)
# ─────────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        zip \
        unzip \
        mariadb-client \
        dos2unix \
        netcat-openbsd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ─────────────────────────────────────────────────────────────
# Configurar Apache MPM
# Eliminar TODOS los enlaces mpm_* y dejar solo mpm_prefork
# (único compatible con mod_php)
# ─────────────────────────────────────────────────────────────
RUN find /etc/apache2/mods-enabled/ -name 'mpm_*.load' -delete \
    && find /etc/apache2/mods-enabled/ -name 'mpm_*.conf' -delete \
    && a2enmod mpm_prefork rewrite

# ─────────────────────────────────────────────────────────────
# Copiar configuración de Apache y PHP
# ─────────────────────────────────────────────────────────────
COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini    /usr/local/etc/php/conf.d/custom.ini

# ─────────────────────────────────────────────────────────────
# Copiar código fuente del proyecto
# ─────────────────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ─────────────────────────────────────────────────────────────
# Entrypoint: gestiona puerto dinámico + init BD en background
# ─────────────────────────────────────────────────────────────
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN dos2unix /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
