FROM composer:2 AS vendor

WORKDIR /usr/share/php-mf2
COPY . .

RUN composer config platform.php 5.6.40 \
    && composer config audit.block-insecure false \
    && composer install --prefer-dist --no-cache --no-interaction

FROM php:5.6-cli

RUN pecl install xdebug-2.5.5 \
    && docker-php-ext-enable xdebug

WORKDIR /usr/share/php-mf2
COPY --from=vendor /usr/share/php-mf2 .
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

CMD ["composer", "--no-interaction", "run", "check-and-test-all"]
