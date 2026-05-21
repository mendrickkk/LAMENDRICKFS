#!/bin/sh
set -e
cd /var/www/html

is_placeholder_jwt() {
    case "$1" in
        ''|build_jwt_passphrase|change_me_jwt_passphrase|REPLACE_WITH_JWT_PASSPHRASE) return 0 ;;
        *) return 1 ;;
    esac
}

is_placeholder_app_secret() {
    case "$1" in
        ''|build-time-secret-set-in-railway-variables|change_me_to_a_long_random_string) return 0 ;;
        *) return 1 ;;
    esac
}

if is_placeholder_jwt "$JWT_PASSPHRASE" && ! is_placeholder_app_secret "$APP_SECRET"; then
    export JWT_PASSPHRASE="$APP_SECRET"
    echo "[jwt] JWT_PASSPHRASE not set — using APP_SECRET (Railway-safe, no DB access)."
fi

echo "[jwt] Ensuring JWT keys..."
php bin/console app:jwt:ensure-keys --no-interaction
