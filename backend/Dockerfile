FROM php:8.2-fpm-alpine
RUN apk add --no-cache icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath intl mbstring
WORKDIR /var/www/html
CMD ["php-fpm"]
