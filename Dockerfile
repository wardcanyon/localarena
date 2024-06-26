# syntax=docker/dockerfile:1

# Comments are provided throughout this file to help you get started.
# If you need more help, visit the Dockerfile reference guide at
# https://docs.docker.com/go/dockerfile-reference/

# Want to help us make this template better? Share your feedback here: https://forms.gle/ybq9Krt8jtBL3iCk7

################################################################################

# The example below uses the PHP Apache image as the foundation for running the app.
# By specifying the "8.2-apache" tag, it will also use whatever happens to be the
# most recent version of that tag when you build your Dockerfile.
# If reproducability is important, consider using a specific digest SHA, like
# php@sha256:99cede493dfd88720b610eb8077c8688d3cca50003d76d1d539b0efc8cca72b4.
FROM php:8.2-apache AS server

# Your PHP application may require additional PHP extensions to be installed
# manually. For detailed instructions for installing extensions can be found, see
# https://github.com/docker-library/docs/tree/master/php#how-to-install-more-php-extensions
# The following code blocks provide examples that you can edit and use.
#
# Add core PHP extensions, see
# https://github.com/docker-library/docs/tree/master/php#php-core-extensions
# This example adds the apt packages for the 'gd' extension's dependencies and then
# installs the 'gd' extension. For additional tips on running apt-get:
# https://docs.docker.com/go/dockerfile-aptget-best-practices/
# RUN apt-get update && apt-get install -y \
#     libfreetype-dev \
#     libjpeg62-turbo-dev \
#     libpng-dev \
# && rm -rf /var/lib/apt/lists/* \
#     && docker-php-ext-configure gd --with-freetype --with-jpeg \
#     && docker-php-ext-install -j$(nproc) gd
#
# Add PECL extensions, see
# https://github.com/docker-library/docs/tree/master/php#pecl-extensions
# This example adds the 'redis' and 'xdebug' extensions.
# RUN pecl install redis-5.3.7 \
#    && pecl install xdebug-3.2.1 \
#    && docker-php-ext-enable redis xdebug

# Install PHP mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Use the default production configuration for PHP runtime arguments, see
# https://github.com/docker-library/docs/tree/master/php#configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV APACHE_DOCUMENT_ROOT /src
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY config/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy app files from the app directory.
#
# TODO: Especially for development purposes, we probably want to mount
# this as a volume instead.
COPY ./src /src
RUN chown -R www-data: /src
RUN chmod -R 755 /src

# Switch to a non-privileged user (defined in the base image) that the app will run under.
# See https://docs.docker.com/go/dockerfile-user-best-practices/
USER www-data

FROM php:8.2-cli AS testenv

# Install `php-ast` from PECL (required for `phan`)
RUN pecl install ast && docker-php-ext-enable ast

# Install PHP mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
# `7z` is required for `composer` to install things.
RUN apt-get update -y && apt-get dist-upgrade -y \
    && apt-get install --no-install-recommends --yes p7zip-full \
    && rm -rf /var/lib/apt/lists/*

RUN composer require --dev phpunit/phpunit ^11
RUN composer require --dev phan/phan
ENV PATH="$PATH:/vendor/bin"

ENV DB_HOST=db
ENV DB_USER=root
ENV DB_PASSWORD_FILE_PATH=/run/secrets/db-password

COPY ./src/localarena_config.inc.php /src/localarena/localarena_config.inc.php
COPY ./src/module /src/localarena/module
COPY ./src/view /src/localarena/view
COPY ./src/vendor /src/localarena/vendor
RUN chown -R www-data: /src
RUN chmod -R 755 /src
