FROM php:5.6-cli

COPY --from=composer:2.2.12 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
      zip \
    && rm -rf /var/cache/apt/

RUN pecl install xdebug-2.5.5 \
    && docker-php-ext-enable xdebug

WORKDIR /usr/share/php-mf2
COPY . .

RUN composer install --prefer-dist --no-cache --no-interaction

CMD ["composer", "--no-interaction", "run", "check-and-test-all"]
