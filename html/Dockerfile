FROM php:8.4-apache

RUN apt-get update && \
    apt-get install -y \
        libsnmp-dev \
        snmp \
        libjpeg-dev \
        libpng-dev \
        libfreetype6-dev \
        libwebp-dev \
        zip \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
        --with-jpeg \
        --with-freetype \
        --with-webp \
    && docker-php-ext-install gd snmp mysqli pdo pdo_mysql

RUN a2enmod rewrite

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf