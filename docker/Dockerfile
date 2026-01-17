FROM php:8.5.1-cli

RUN apt-get update && apt-get install -y \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

RUN pecl install pcov && docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/app
