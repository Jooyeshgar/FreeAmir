# =============================================================================
# Stage 1: Builder — install Composer deps (no dev) with all build-time tools
# =============================================================================
FROM php:8.3-fpm AS builder

LABEL maintainer="FreeAmir"

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    unzip \
    libpng-dev \
    libonig-dev \
    libssl-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        gd \
        intl \
        zip \
        bcmath \
        soap \
        mbstring \
        xml \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 bootstrap/cache storage \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# =============================================================================
#Stage 2: All-in-One — PHP-FPM + Nginx + MySQL + Supervisor
#=============================================================================

FROM php:8.3-fpm AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng16-16 \
    libonig5 \
    libxml2 \
    libicu76 \
    libzip5 \
    libpq5 \
    libfcgi-bin \
    procps \
    nginx \
    mariadb-server \
    mariadb-client \
    supervisor \
    curl \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -fsSL https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    -o /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/all-in-one/php.ini $PHP_INI_DIR/conf.d/99-app.ini

COPY docker/all-in-one/php-fpm/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/all-in-one/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/all-in-one/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/all-in-one/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/all-in-one/init-mysql.sh /usr/local/bin/init-mysql.sh
COPY docker/all-in-one/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/init-mysql.sh /usr/local/bin/entrypoint.sh

COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

COPY --chown=www-data:www-data .env.example /var/www/html/.env.example

RUN mkdir -p \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
    && chmod -R 775 \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

RUN mkdir -p /var/lib/mysql /run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql /run/mysqld

# Create sites-enabled symlink for nginx
RUN mkdir -p /etc/nginx/sites-enabled \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

WORKDIR /var/www/html

EXPOSE 80 3306

HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]