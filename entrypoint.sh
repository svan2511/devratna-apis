#!/bin/sh
set -e

echo "========================================"
echo "Starting Laravel Application on Render"
echo "========================================"

# Clear old cached files
rm -f bootstrap/cache/*.php



# Clear all caches (DB-independent: cache:clear with database driver would fail
# before migrations create the `cache` table — so force file driver here)
echo "Clearing Laravel caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
CACHE_STORE=file php artisan cache:clear || true

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