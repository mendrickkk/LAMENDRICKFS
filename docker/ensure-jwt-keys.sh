#!/bin/sh
# Helper used by docker-compose local path; on Railway, entrypoint.sh calls
# php bin/console app:jwt:ensure-keys directly after resolving JWT_PASSPHRASE.
set -e
cd /var/www/html

is_placeholder_jwt() {
    case "$1" in
        ''|build_jwt_passphrase|change_me_jwt_passphrase|REPLACE_WITH_JWT_PASSPHRASE) return 0 ;;
        *) return 1 ;;
    esac
}

if is_placeholder_jwt "${JWT_PASSPHRASE:-}"; then
    export JWT_PASSPHRASE="$APP_SECRET"
    echo "[jwt] JWT_PASSPHRASE not set — using APP_SECRET."
fi

echo "[jwt] Ensuring JWT keys..."
php bin/console app:jwt:ensure-keys --no-interaction
