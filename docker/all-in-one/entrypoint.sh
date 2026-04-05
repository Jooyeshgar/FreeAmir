#!/bin/bash

set -e

echo "🚀 Starting FreeAmir All-in-One Container..."

if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "📦 Initializing MySQL database..."
    /usr/local/bin/init-mysql.sh
fi

echo "⏳ Waiting for MySQL to be ready..."

until mysqladmin ping -h localhost --silent; do
    sleep 1
done

echo "✅ MySQL is ready"

cd /var/www/html

# Create .env if it doesn't exist
if [ ! -f .env ]; then

    echo "📝 Creating .env file from .env.example..."

    cp .env.example .env

    # Set database connection
    sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env
    sed -i 's/DB_HOST=.*/DB_HOST=127.0.0.1/' .env
    sed -i 's/DB_PORT=.*/DB_PORT=3306/' .env
    sed -i 's/DB_DATABASE=.*/DB_DATABASE=freeamir/' .env
    sed -i 's/DB_USERNAME=.*/DB_USERNAME=freeamir/' .env
    sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=freeamir/' .env

fi

if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

FIRST_RUN=false

if ! php artisan migrate:status 2>/dev/null | grep -q "Migration name"; then
    FIRST_RUN=true
fi

if [ "$FIRST_RUN" = true ]; then
    echo "🎯 First run detected - setting up database..."
    echo "📊 Running migrations..."
    php artisan migrate --force

    # Run seeders if they exist
    if [ -d "database/seeders" ] && [ "$(ls -A database/seeders)" ]; then
        echo "🌱 Running database seeders..."
        php artisan db:seed --force || echo "⚠️ Seeding failed or no seeders found"
    fi

    echo "✅ Database setup complete"

else
    echo "♻️ Existing installation detected - running migrations..."
    php artisan migrate --force
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    echo "⚡ Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

if [ ! -L public/storage ]; then
    echo "🔗 Creating storage symlink..."
    php artisan storage:link
fi

echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
echo "✨ FreeAmir is ready!"
echo "📍 Access the application at: http://localhost"
echo "🗄️ MySQL is running on port 3306"

# Execute the main command (supervisord)
exec "$@"
