# Dockerfile-app

# Use PHP 8.5 with Apache for the base image
FROM  php:8.5-apache

# Set default environment variables
ENV YII_ENV=prod
ENV YII_DEBUG=false

# Enable the Rewrite Apache mod
RUN cd /etc/apache2/mods-enabled && \
    ln -s ../mods-available/rewrite.load && \
    ln -s ../mods-available/headers.load 

# Install required PHP extensions
# -- GD and system dependencies
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    git \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html/api

# Copy composer files first for caching
COPY api/composer.json api/composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy the rest of the application
COPY ./api/ .

# Copy additional configs
COPY files/api/config/* ./config/
COPY files/api/web/index.php ./web/
COPY files/api/web/robots.txt ./web/

# Set permissions
RUN mkdir -p /var/www/html/api/runtime /var/www/html/api/web/assets \
    && chown -R www-data:www-data /var/www/html/api \
    && chmod -R 755 /var/www/html/api \
    && chmod -R 777 /var/www/html/api/runtime \
    && chmod -R 777 /var/www/html/api/web/assets

# Copy HTTP server config
COPY ./dockerfile/000-default.conf /etc/apache2/sites-available/
