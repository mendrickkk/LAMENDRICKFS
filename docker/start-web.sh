#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PORT

if ! command -v envsubst >/dev/null 2>&1; then
    echo "ERROR: envsubst not found"
    exit 1
fi

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

echo "Starting PHP-FPM..."
# Background PHP-FPM (docker-php-entrypoint sets up pool config)
docker-php-entrypoint php-fpm -D

echo "Starting Nginx on 0.0.0.0:${PORT}..."
exec nginx -g 'daemon off;'
