FROM php:8.2-apache

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

RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite

COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini    /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
COPY . .

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN dos2unix /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]