#!/bin/sh

ROLE="${CONTAINER_ROLE:-serve}"

php artisan optimize -q
php artisan filament:optimize -q

case "$ROLE" in
  serve)
    exec frankenphp run --config /etc/caddy/Caddyfile
    ;;
  worker)
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  *)
    echo "Unknown CONTAINER_ROLE: $ROLE"
    exit 1
    ;;
esac

