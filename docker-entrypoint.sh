#!/bin/sh
set -e

APP_DIR=/var/www/html

echo "[entrypoint] Starting Green Canvas..."

# Ensure .env exists
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi

# Set APP_URL from Fly.io machine name if not already set explicitly
if [ -n "$FLY_APP_NAME" ] && [ -z "$APP_URL_SET" ]; then
    sed -i "s|APP_URL=.*|APP_URL=https://${FLY_APP_NAME}.fly.dev|" "$APP_DIR/.env"
fi

# Generate app key if not already set
if ! grep -q "^APP_KEY=base64:" "$APP_DIR/.env"; then
    echo "[entrypoint] Generating application key..."
    php "$APP_DIR/artisan" key:generate --force
fi

# Create SQLite file if it doesn't exist
if [ ! -f "$APP_DIR/database/database.sqlite" ]; then
    touch "$APP_DIR/database/database.sqlite"
    chown www-data:www-data "$APP_DIR/database/database.sqlite"
    chmod 664 "$APP_DIR/database/database.sqlite"
fi

# Run migrations
echo "[entrypoint] Running migrations..."
php "$APP_DIR/artisan" migrate --force

# Seed if database is empty (user count as a proxy)
USER_COUNT=$(php "$APP_DIR/artisan" tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "[entrypoint] Seeding demo data..."
    php "$APP_DIR/artisan" db:seed --force
fi

# Warm caches
echo "[entrypoint] Caching config, routes, views..."
php "$APP_DIR/artisan" config:cache
php "$APP_DIR/artisan" route:cache
php "$APP_DIR/artisan" view:cache

echo "[entrypoint] Ready. Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
