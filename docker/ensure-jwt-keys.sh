#!/bin/sh
set -e
cd /var/www/html
echo "[jwt] Checking JWT keys (no database access)..."
php bin/console app:jwt:ensure-keys --no-interaction
