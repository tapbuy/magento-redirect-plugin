FROM php:8.3-cli

# Install system dependencies and PHP extensions matching the CI environment
# CI uses: bcmath, ctype, curl, dom, gd, hash, iconv, intl, mbstring, openssl,
#          pdo_mysql, simplexml, soap, xsl, zip
# ctype, curl, dom, hash, iconv, json, mbstring, openssl, simplexml are pre-built in this image.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libxslt1-dev \
        libzip-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        pdo_mysql \
        soap \
        sockets \
        xsl \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install global PHP code quality tools (used by `make phpmd`, `make phpcs`, `make lint`)
# Versions are pinned to keep lint results deterministic across CI and local runs.
# To upgrade: bump the version here, rebuild the image, and commit the change.
ENV COMPOSER_HOME=/root/.composer
RUN composer global config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true \
    && composer global require --no-interaction \
        phpmd/phpmd:2.15.0 \
        squizlabs/php_codesniffer:3.13.5 \
        magento/magento-coding-standard:40
ENV PATH="/root/.composer/vendor/bin:$PATH"
