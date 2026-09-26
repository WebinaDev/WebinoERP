# PHP_IMAGE / COMPOSER_IMAGE / FRANKENPHP_IMAGE / DOCKER_CLI_IMAGE:
# override for Iranian mirrors, e.g. hub.hamdocker.ir/library/php:8.3-cli-bookworm
ARG PHP_IMAGE=php:8.3-cli-bookworm
ARG COMPOSER_IMAGE=composer:2
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1-php8.3-bookworm
ARG DOCKER_CLI_IMAGE=docker:27-cli

# BuildKit does not allow COPY --from=${VAR}; use named stages instead.
FROM ${COMPOSER_IMAGE} AS composer_src
FROM ${FRANKENPHP_IMAGE} AS frankenphp_src
FROM ${DOCKER_CLI_IMAGE} AS docker_cli_src

FROM ${PHP_IMAGE}

ARG REDIS_PECL_VERSION=6.0.2

COPY docker/php/install-redis.sh /tmp/install-redis.sh

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git unzip libzip-dev libpq-dev libpng-dev libonig-dev libxml2-dev libsqlite3-dev \
        libbrotli-dev curl ca-certificates \
    && docker-php-ext-install dom xml zip pdo_sqlite pdo_pgsql pgsql mbstring pcntl bcmath gd opcache \
    && chmod +x /tmp/install-redis.sh \
    && REDIS_PECL_VERSION="$REDIS_PECL_VERSION" /tmp/install-redis.sh \
    && rm -f /tmp/install-redis.sh \
    && docker-php-ext-enable redis opcache \
    && rm -rf /var/lib/apt/lists/*

RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer_src /usr/bin/composer /usr/bin/composer
COPY --from=frankenphp_src /usr/local/bin/frankenphp /usr/local/bin/frankenphp
# Host Docker for LocalSameVpsProvisioner (docker.sock mounted at runtime).
COPY --from=docker_cli_src /usr/local/bin/docker /usr/local/bin/docker
COPY --from=docker_cli_src /usr/local/libexec/docker/cli-plugins/docker-compose /usr/local/libexec/docker/cli-plugins/docker-compose
COPY --from=docker_cli_src /usr/local/libexec/docker/cli-plugins/docker-buildx /usr/local/libexec/docker/cli-plugins/docker-buildx
RUN chmod +x /usr/local/bin/frankenphp /usr/local/bin/docker \
    && mkdir -p /usr/local/lib/docker/cli-plugins \
    && ln -sf /usr/local/libexec/docker/cli-plugins/docker-compose /usr/local/lib/docker/cli-plugins/docker-compose \
    && ln -sf /usr/local/libexec/docker/cli-plugins/docker-buildx /usr/local/lib/docker/cli-plugins/docker-buildx \
    && chmod +x /usr/local/libexec/docker/cli-plugins/docker-compose \
        /usr/local/libexec/docker/cli-plugins/docker-buildx

WORKDIR /var/www/html
