FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        dcron \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo 'upload_max_filesize = 200M' >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'post_max_size = 210M' >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'display_errors = Off' >> /usr/local/etc/php/conf.d/workeddy.ini \
 && echo 'log_errors = On' >> /usr/local/etc/php/conf.d/workeddy.ini

RUN { \
    echo '[www]'; \
    echo 'pm = dynamic'; \
    echo 'pm.max_children = 20'; \
    echo 'pm.start_servers = 5'; \
    echo 'pm.min_spare_servers = 5'; \
    echo 'pm.max_spare_servers = 15'; \
} > /usr/local/etc/php-fpm.d/zz-workeddy-pool.conf

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader --no-interaction

COPY . .

COPY infrastructure/docker/api-entrypoint.sh /usr/local/bin/api-entrypoint.sh
COPY infrastructure/docker/run-loop.sh /usr/local/bin/run-loop.sh
COPY infrastructure/docker/write-crontab.sh /usr/local/bin/write-crontab.sh
RUN chmod +x /usr/local/bin/api-entrypoint.sh /usr/local/bin/run-loop.sh /usr/local/bin/write-crontab.sh

ENTRYPOINT ["/usr/local/bin/api-entrypoint.sh"]
CMD ["php-fpm", "-F"]
