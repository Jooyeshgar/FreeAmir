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
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        intl \
        zip \
        bcmath \
        soap \
        mbstring \
        xml \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www/html

# Copy full application source so post-autoload-dump Artisan scripts can run
COPY . /var/www/html

# Install Composer and production dependencies
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 bootstrap/cache storage \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# =============================================================================
# Stage 2: Production — lean PHP-FPM runtime image
# =============================================================================
FROM php:8.3-fpm AS production

# Runtime-only system libraries + php-fpm-healthcheck dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    libfcgi-bin \
    procps \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install php-fpm health-check script
RUN curl -fsSL https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    -o /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Copy compiled PHP extensions and config from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/bin/docker-php-ext-* /usr/local/bin/

# Use PHP's recommended production ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# App-specific PHP settings (upload limits, opcache, etc.)
COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-app.ini

# PHP-FPM pool config (enables /status endpoint for healthcheck)
COPY docker/production/php-fpm/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Copy entrypoint
COPY docker/production/bootstrap.sh /usr/local/bin/bootstrap.sh
RUN chmod +x /usr/local/bin/bootstrap.sh

# Copy application code + vendor from builder
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Ensure storage and cache dirs exist and are writable
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

WORKDIR /var/www/html

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/bootstrap.sh"]
CMD ["php-fpm"]
