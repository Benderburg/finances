ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm-alpine
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apk add --no-cache icu-dev oniguruma-dev unzip \
    && docker-php-ext-install pdo_mysql bcmath intl mbstring
WORKDIR /var/www/html
CMD ["php-fpm"]
