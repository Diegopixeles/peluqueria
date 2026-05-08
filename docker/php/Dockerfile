FROM php:8.2-apache

# CACHEBUST: cambia este número para forzar rebuild sin caché en Railway
ARG CACHEBUST=3

# ── Dependencias del sistema y extensiones PHP ──────────────────────────────
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

# ── MPM: eliminar TODOS los MPMs activos y activar solo mpm_prefork ─────────
# find -delete es más fiable que rm con globs en shell
# php:8.2-apache viene con mpm_event activo por defecto
RUN find /etc/apache2/mods-enabled -name "mpm_*" -delete \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite \
    && echo "✅ MPMs activos:" && ls /etc/apache2/mods-enabled/mpm_*

# ── Configuración Apache ─────────────────────────────────────────────────────
COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini    /usr/local/etc/php/conf.d/custom.ini

# ── Código de la aplicación ──────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ── Entrypoint ───────────────────────────────────────────────────────────────
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN dos2unix /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]