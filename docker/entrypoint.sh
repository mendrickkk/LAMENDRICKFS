#!/bin/sh
set -e

if [ -z "${DATABASE_URL:-}" ]; then
    echo "DATABASE_URL is not set. Check your .env file or compose environment."
    exit 1
fi

echo "Waiting for database..."
until php -r '
    $url = getenv("DATABASE_URL");
    if (!$url) { exit(1); }
    $parts = parse_url($url);
    $host = $parts["host"] ?? "mysql";
    $port = $parts["port"] ?? 3306;
    $user = $parts["user"] ?? "";
    $pass = $parts["pass"] ?? "";
    try {
        new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    sleep 2
done
echo "Database is ready."

if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT key pair..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

echo "Running database migrations..."
if ! php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
    echo "WARNING: migrations did not complete (database may already be initialized)."
    echo "If the app fails to load data, run: docker compose exec app php bin/console doctrine:migrations:status"
fi

echo "Warming up cache..."
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
php bin/console cache:warmup --env="${APP_ENV:-prod}"

chown -R www-data:www-data var config/jwt public/uploads 2>/dev/null || true

if [ "$1" = "php-fpm" ]; then
    exec docker-php-entrypoint php-fpm
fi

exec "$@"
