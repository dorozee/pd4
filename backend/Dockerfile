FROM php:8.4-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip zip curl ca-certificates \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath gd zip opcache posix \
    && curl -fsSL https://github.com/phpredis/phpredis/archive/6.1.0.tar.gz -o /tmp/redis.tar.gz \
    && mkdir -p /usr/src/php/ext/redis \
    && tar -xzf /tmp/redis.tar.gz -C /usr/src/php/ext/redis --strip-components=1 \
    && rm /tmp/redis.tar.gz \
    && docker-php-ext-install -j$(nproc) redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# opcache tuning for dev
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-dev.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
