# Production image: FrankenPHP (Caddy + PHP 8.4) serving public/, the same image runs
# the scheduler worker with a different command. Runtime configuration comes from
# environment variables (APP_SECRET, DATABASE_URL, ...), see DEPLOYMENT.md.
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions pdo_sqlite gd intl opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV APP_ENV=prod \
    SERVER_NAME=:80

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-progress --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && php bin/console asset-map:compile \
 && mkdir -p var/data var/share var/log var/cache \
 && chmod -R 777 var

EXPOSE 80
