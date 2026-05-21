#!/bin/sh
# Runs once per Railway deploy BEFORE traffic switches (release phase).
set -e

export SYMFONY_DEPRECATIONS_HELPER=disabled

cd /var/www/html

if [ -z "${DATABASE_URL:-}" ]; then
    echo "ERROR: DATABASE_URL is not set."
    exit 1
fi

echo "[release] Waiting for database (max 90s)..."
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

if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

# Mark bulk schema migration when tables already exist (avoids "table already exists")
if php -r '
    $url = getenv("DATABASE_URL");
    $parts = parse_url($url);
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", $parts["host"], $parts["port"] ?? 3306, ltrim($parts["path"] ?? "", "/"));
    $pdo = new PDO($dsn, $parts["user"], $parts["pass"]);
    exit($pdo->query("SHOW TABLES LIKE \"activity_log\"")->fetch() ? 0 : 1);
' 2>/dev/null; then
    echo "[release] activity_log exists — marking Version20260323104223 as executed."
    php bin/console doctrine:migrations:version 'DoctrineMigrations\\Version20260323104223' --add --no-interaction 2>/dev/null || true
fi

echo "[release] Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[release] Repairing plain-text passwords (hash + verify)..."
php bin/console app:user:repair-login --all-plain --verify --no-interaction

echo "[release] Ensuring default admin placeholder (only kurttruk1234@gmail.com / adminkurt)..."
php bin/console app:seed-default-admin --no-interaction

if [ -f .env ]; then
    rm -f .env
fi

echo "[release] Warming cache..."
php bin/console cache:clear --env="${APP_ENV:-prod}" --no-warmup
php bin/console cache:warmup --env="${APP_ENV:-prod}"

echo "[release] Done."
