#!/bin/sh
set -e

export SYMFONY_DEPRECATIONS_HELPER=disabled

is_web_start() {
    case "$1" in
        */start-web.sh|start-web.sh) return 0 ;;
    esac
    return 1
}

if [ -z "${APP_SECRET:-}" ] || [ "${APP_SECRET}" = "change_me_to_a_long_random_string" ] || [ "${APP_SECRET}" = "build-time-secret-set-in-railway-variables" ]; then
    echo "ERROR: Set a real APP_SECRET in Railway variables."
    exit 1
fi

# Railway: migrations/cache run in release.sh — start HTTP immediately for healthcheck
if is_web_start "$1" && { [ -n "${RAILWAY_ENVIRONMENT:-}" ] || [ -n "${RAILWAY_SERVICE_NAME:-}" ]; }; then
    sh /usr/local/bin/ensure-jwt-keys.sh
    chown -R www-data:www-data var config/jwt public/uploads 2>/dev/null || true
    chmod -R ug+rwX var config/jwt public/uploads 2>/dev/null || true
    echo "Railway PORT=${PORT:-not set} — starting web server."
    exec "$1"
fi

# Local Docker Compose: full setup before php-fpm
if [ -z "${DATABASE_URL:-}" ]; then
    echo "ERROR: DATABASE_URL is not set."
    exit 1
fi

echo "Waiting for database (max 90s)..."
attempt=0
until php -r '
    $url = getenv("DATABASE_URL");
    $parts = parse_url($url);
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s", $parts["host"], $parts["port"] ?? 3306),
            $parts["user"] ?? "",
            $parts["pass"] ?? "",
            [PDO::ATTR_TIMEOUT => 3]
        );
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 45 ]; then
        echo "ERROR: Database not reachable."
        exit 1
    fi
    sleep 2
done

sh /usr/local/bin/ensure-jwt-keys.sh

if php -r '
    $url = getenv("DATABASE_URL");
    $parts = parse_url($url);
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $parts["host"], $parts["port"] ?? 3306, ltrim($parts["path"] ?? "", "/"));
    $pdo = new PDO($dsn, $parts["user"], $parts["pass"]);
    exit($pdo->query("SHOW TABLES LIKE \"activity_log\"")->fetch() ? 0 : 1);
' 2>/dev/null; then
    php bin/console doctrine:migrations:version 'DoctrineMigrations\\Version20260323104223' --add --no-interaction 2>/dev/null || true
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || \
    echo "WARNING: migrations did not complete."

php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
php bin/console cache:warmup --env="${APP_ENV:-prod}"

chown -R www-data:www-data var config/jwt public/uploads 2>/dev/null || true
chmod -R ug+rwX var config/jwt public/uploads 2>/dev/null || true

if [ "$1" = "php-fpm" ]; then
    exec docker-php-entrypoint php-fpm
fi

if is_web_start "$1"; then
    exec "$1"
fi

exec "$@"
