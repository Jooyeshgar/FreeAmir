#!/usr/bin/env bash
set -e

echo "[bootstrap] Starting FreeAmir..."

# Wait for database to be ready
echo "[bootstrap] Waiting for database..."
max_attempts=30
attempt=0
until php artisan db:show 2>/dev/null || [ $attempt -eq $max_attempts ]; do
    attempt=$((attempt + 1))
    echo "[bootstrap] Database not ready, waiting... ($attempt/$max_attempts)"
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "[bootstrap] ERROR: Database connection timeout"
    exit 1
fi

# Create .env if it doesn't exist
if [ ! -f /var/www/html/.env ]; then
    echo "[bootstrap] Creating .env from example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Generate app key if not set in .env file
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "[bootstrap] Generating application key..."
    php artisan key:generate --force
fi

# Check if this is first run by checking if migrations table exists
FIRST_RUN=false
if ! php artisan migrate:status 2>/dev/null | grep -q "Migration name"; then
    FIRST_RUN=true
    echo "[bootstrap] First run detected"
fi

# Run migrations
echo "[bootstrap] Running database migrations..."
php artisan migrate --force

# Seed only on first run
if [ "$FIRST_RUN" = true ]; then
    echo "[bootstrap] Seeding database..."
    php artisan db:seed --force
else
    echo "[bootstrap] Database already initialized, skipping seed"
fi

# Optimize only in production
if [ "${APP_ENV:-production}" = "production" ]; then
    echo "[bootstrap] Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "[bootstrap] Development mode, skipping optimization"
fi

echo "[bootstrap] Bootstrap complete!"
exec "$@"
