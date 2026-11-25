# Local development image. Production runs on Plesk PHP 8.4-FPM (see /deploy).
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl zip gd exif pcntl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# OPcache: the source is a Windows bind mount, so file reads are slow. Cache compiled
# scripts and only re-check timestamps every 2 seconds.
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=24M'; \
        echo 'max_execution_time=120'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.max_accelerated_files=30000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/hazri.ini

WORKDIR /var/www
