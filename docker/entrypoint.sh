#!/bin/sh
set -e

export SYMFONY_DEPRECATIONS_HELPER=disabled

is_web_start() {
    case "$1" in
        */start-web.sh|start-web.sh) return 0 ;;
    esac
    return 1
}

if [ -z "${DATABASE_URL:-}" ]; then
    echo "ERROR: DATABASE_URL is not set. Link MySQL to this service in Railway."
    exit 1
fi

echo "Waiting for database (max 90s)..."
attempt=0
max_attempts=45
until php -r '
    $url = getenv("DATABASE_URL");
    if (!$url) { exit(1); }
    $parts = parse_url($url);
    $host = $parts["host"] ?? "";
    $port = $parts["port"] ?? 3306;
    $user = $parts["user"] ?? "";
    $pass = $parts["pass"] ?? "";
    if ($host === "") { exit(1); }
    try {
        new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "ERROR: Database not reachable. Check DATABASE_URL and MySQL service link."
        echo "DATABASE_URL host: $(php -r 'echo parse_url(getenv("DATABASE_URL"), PHP_URL_HOST) ?: "unknown";')"
        exit 1
    fi
    sleep 2
done
echo "Database is ready."

if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT key pair..."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

echo "Running database migrations..."
if ! php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
    echo "WARNING: migrations failed — syncing Version20260323104223 if schema already exists..."
    if php -r '
        require "vendor/autoload.php";
        $url = getenv("DATABASE_URL");
        $parts = parse_url($url);
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $parts["host"], $parts["port"] ?? 3306, ltrim($parts["path"] ?? "", "/"));
        $pdo = new PDO($dsn, $parts["user"], $parts["pass"]);
        $exists = $pdo->query("SHOW TABLES LIKE \"activity_log\"")->fetch();
        exit($exists ? 0 : 1);
    ' 2>/dev/null; then
        php bin/console doctrine:migrations:version 'DoctrineMigrations\\Version20260323104223' --add --no-interaction 2>/dev/null || true
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || \
            echo "WARNING: some migrations could not be applied; check doctrine:migrations:status"
    else
        echo "WARNING: migrations did not complete (database may already be initialized)."
    fi
fi

echo "Warming up cache..."
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
php bin/console cache:warmup --env="${APP_ENV:-prod}"

# Baked .env is for Docker build only — remove so PHP-FPM uses Railway variables
if [ -f .env ] && { [ -n "${RAILWAY_ENVIRONMENT:-}" ] || [ -n "${RAILWAY_SERVICE_NAME:-}" ]; }; then
    echo "Removing build-time .env (using Railway environment variables)."
    rm -f .env
    php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
    php bin/console cache:warmup --env="${APP_ENV:-prod}"
fi

if [ -z "${APP_SECRET:-}" ] || [ "${APP_SECRET}" = "change_me_to_a_long_random_string" ] || [ "${APP_SECRET}" = "build-time-secret-set-in-railway-variables" ]; then
    echo "ERROR: Set a real APP_SECRET in Railway variables."
    exit 1
fi

chown -R www-data:www-data var config/jwt public/uploads 2>/dev/null || true
chmod -R ug+rwX var config/jwt public/uploads 2>/dev/null || true

if [ "$1" = "php-fpm" ]; then
    exec docker-php-entrypoint php-fpm
fi

if is_web_start "$1"; then
    echo "Railway PORT=${PORT:-not set}"
    exec "$1"
fi

exec "$@"
