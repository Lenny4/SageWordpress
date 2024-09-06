# https://github.com/StephenMiracle/frankenwp/blob/main/Dockerfile
# https://hub.docker.com/r/wpeverywhere/frankenwp/tags

FROM wpeverywhere/frankenwp:latest-php8.2 AS app_php

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

WORKDIR /var/www/html/

COPY --link ./public ./
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY --link ./docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link ./docker/Caddyfile /etc/caddy/Caddyfile
COPY --link ./docker/php.ini /usr/local/etc/php/php.ini
RUN chmod +x /usr/local/bin/docker-entrypoint

RUN install-php-extensions xdebug-stable

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
