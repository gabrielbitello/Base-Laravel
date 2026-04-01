FROM dunglas/frankenphp:1-php8.5

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    intl \
    pdo_mysql \
    zip \
    bcmath \
    exif \
    gd \
    opcache

ARG NODE_VERSION=22.16.0
RUN ARCH=$(dpkg --print-architecture | sed 's/amd64/x64/') \
    && curl -fsSL "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${ARCH}.tar.xz" \
    | tar -xJ -C /usr/local --strip-components=1 --no-same-owner \
    && node --version && npm --version

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/custom.ini
COPY Caddyfile /etc/caddy/Caddyfile

WORKDIR /var/www

EXPOSE 80 443 5173

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
