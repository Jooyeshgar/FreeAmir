#!/usr/bin/env bash
set -e

echo "[bootstrap] Starting FreeAmir..."

# Generate app key if not already set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    echo "[bootstrap] Generating application key..."
    php artisan key:generate --force
fi

# Run database migrations
echo "[bootstrap] Running database migrations..."
php artisan migrate --force

# Seed the database on first run (only if the users table is empty)
if php artisan tinker --execute="exit(\\App\\Models\\User::count() > 0 ? 0 : 1);" 2>/dev/null; then
    echo "[bootstrap] Database already seeded, skipping."
else
    echo "[bootstrap] Seeding database..."
    php artisan db:seed --force
fi

# Clear and warm up caches for production
echo "[bootstrap] Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[bootstrap] Bootstrap complete. Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
