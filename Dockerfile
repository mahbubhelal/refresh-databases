FROM php:8.3.29-cli-alpine3.23

RUN apk update && apk add --no-cache \
    $PHPIZE_DEPS zip unzip mysql-client unixodbc-dev

# Install sqlserver related packages
RUN curl -fSL -o msodbcsql18_18.5.1.1-1_amd64.apk https://download.microsoft.com/download/fae28b9a-d880-42fd-9b98-d779f0fdd77f/msodbcsql18_18.5.1.1-1_amd64.apk && \
    apk add --allow-untrusted msodbcsql18_18.5.1.1-1_amd64.apk && \
    rm msodbcsql18_18.5.1.1-1_amd64.apk && \
    apk add --no-cache unixodbc-dev && \
    pecl install pdo_sqlsrv && \
    docker-php-ext-enable pdo_sqlsrv

# Configure OpenSSL to use legacy settings
COPY docker/openssl_legacy.conf /etc/ssl/openssl_legacy.cnf
ENV OPENSSL_CONF=/etc/ssl/openssl_legacy.cnf

# Configure mysql client to disable ssl verification
COPY docker/test-mysql.cnf /etc/my.cnf.d/test-mysql.cnf

RUN docker-php-ext-install mysqli pdo_mysql

RUN pecl install pcov && docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/app
