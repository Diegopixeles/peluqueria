FROM php:8.2-apache

# ─────────────────────────────────────────────────────────────
# Instalar extensiones PHP + cliente MySQL (para el entrypoint)
# ─────────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        zip \
        unzip \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ─────────────────────────────────────────────────────────────
# Habilitar mod_rewrite de Apache
# ─────────────────────────────────────────────────────────────
RUN a2enmod rewrite

# ─────────────────────────────────────────────────────────────
# Copiar configuración de Apache y PHP
# ─────────────────────────────────────────────────────────────
COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini    /usr/local/etc/php/conf.d/custom.ini

# ─────────────────────────────────────────────────────────────
# Copiar el código fuente del proyecto
# (En local se monta como volumen; en Railway se copia aquí)
# ─────────────────────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ─────────────────────────────────────────────────────────────
# Entrypoint: ajusta el puerto de Apache y ejecuta el init SQL
# ─────────────────────────────────────────────────────────────
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Railway asigna el puerto dinámicamente via $PORT
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
