#!/bin/sh
set -e

PORT="${PORT:-8080}"
export PORT

envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

echo "Starting PHP-FPM and Nginx on port ${PORT}..."
php-fpm -D
exec nginx -g 'daemon off;'
