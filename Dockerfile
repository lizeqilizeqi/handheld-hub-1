FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libwebp-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        'Alias /admin /var/www/html/admin' \
        'Alias /storage/handhelds /var/www/html/storage/handhelds' \
        '<Directory /var/www/html/admin>' \
        '    Require all granted' \
        '</Directory>' \
        '<Directory /var/www/html/storage/handhelds>' \
        '    Options -Indexes' \
        '    Require all granted' \
        '</Directory>' \
        >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
