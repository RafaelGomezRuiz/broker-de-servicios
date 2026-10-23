# ============================================================
# broker-api
#
# UNA sola imagen para los tres Deployments de Kubernetes. La variable
# ROL decide qué arranca el contenedor (ver docker/start.sh):
#   ROL=api        php-fpm + nginx (por defecto)
#   ROL=worker     cola de envío de correos
#   ROL=scheduler  tareas programadas (retención, verificaciones)
#
# Las imágenes base salen de nuestro ACR en el pipeline para evitar el
# rate limit de Docker Hub. Para un build local basta con:
#   docker build -t broker-api .
# ============================================================
ARG BASE_REGISTRY=docker.io/library/

# ============================================================
# Etapa 1: dependencias con Composer
# ============================================================
FROM ${BASE_REGISTRY}php:8.3-cli AS build

WORKDIR /app

RUN apt-get update && apt-get install -y libzip-dev zip unzip git curl \
    && docker-php-ext-install zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Primero solo los manifiestos: la capa de vendor se reutiliza mientras no cambien.
COPY composer.json composer.lock ./
ENV COMPOSER_MAX_PARALLEL_HTTP=1
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ============================================================
# Etapa 2: runtime PHP-FPM + nginx + supervisor
# ============================================================
FROM ${BASE_REGISTRY}php:8.3-fpm

RUN apt-get update && apt-get install -y \
        nginx \
        supervisor \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath opcache pcntl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /run/nginx /run/php /var/www/html /var/log/supervisor

COPY --from=build /app /var/www/html

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-broker.ini
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# PHP-FPM por TCP, igual que en SGRH.
RUN echo "listen = 127.0.0.1:9000" > /usr/local/etc/php-fpm.d/zz-docker.conf

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

ENV ROL=api
EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
