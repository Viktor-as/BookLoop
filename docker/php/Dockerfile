FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y --no-install-recommends \
        unzip \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        netcat-openbsd \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip opcache \
    && a2enmod rewrite headers \
    && printf '%s\n' 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}/!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf /etc/apache2/sites-available/000-default.conf

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-recommended.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Build-time only: SQLite satisfies Doctrine while the container image is built (runtime uses MySQL from compose).
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=prod \
    APP_DEBUG=0 \
    APP_SECRET=docker-build-only-change-at-runtime \
    JWT_PASSPHRASE=docker-build-only \
    DATABASE_URL="sqlite:////tmp/sf-docker-build.db"

COPY . .

RUN mkdir -p var/cache var/log var/share public \
    && touch /tmp/sf-docker-build.db \
    && composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-scripts \
    && php bin/console importmap:install --no-interaction --env=prod \
    && php bin/console asset-map:compile --no-interaction --env=prod \
    && php bin/console assets:install public --no-interaction --env=prod \
    && php bin/console cache:warmup --no-interaction --no-ansi --env=prod \
    && rm -f /tmp/sf-docker-build.db

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
