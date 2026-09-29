#!/bin/sh
set -e

echo "========================================"
echo "Starting Laravel Application on Render"
echo "========================================"

# Clear old cached files
rm -f bootstrap/cache/*.php



# Clear all caches
echo "Clearing Laravel caches..."
php artisan optimize:clear

# Wait for PostgreSQL
echo "Waiting for PostgreSQL connection..."

until nc -z $DB_HOST $DB_PORT; do
  echo "PostgreSQL is unavailable - sleeping"
  sleep 2
done

echo "PostgreSQL is up!"

# Run Migrations
echo "Running database migrations..."
php artisan migrate --seed --force


# Final Optimizations (After keys are set)
echo "Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "========================================"
echo "✅ Laravel Entrypoint Completed Successfully!"
echo "========================================"

# Start Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf