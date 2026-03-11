FROM dunglas/frankenphp:1-php8.5

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    intl \
    pdo_mysql \
    mysqli \
    zip \
    bcmath \
    exif \
    gd \
    opcache

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY Caddyfile /etc/caddy/Caddyfile

WORKDIR /var/www

EXPOSE 80 443 5173

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
