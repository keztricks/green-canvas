#!/bin/sh
set -e

APP_DIR=/var/www/html

echo "[entrypoint] Starting Green Canvas..."

# Ensure .env exists
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
fi

# Override APP_URL if provided via environment (App Runner, Fly.io, etc.)
if [ -n "$APP_URL" ] && [ "$APP_URL" != "http://localhost" ]; then
    sed -i "s|APP_URL=.*|APP_URL=$APP_URL|" "$APP_DIR/.env"
elif [ -n "$FLY_APP_NAME" ]; then
    sed -i "s|APP_URL=.*|APP_URL=https://${FLY_APP_NAME}.fly.dev|" "$APP_DIR/.env"
fi

# Generate app key if not already set
if ! grep -q "^APP_KEY=base64:" "$APP_DIR/.env"; then
    echo "[entrypoint] Generating application key..."
    php "$APP_DIR/artisan" key:generate --force
fi

# Restore database from Litestream replica (S3) if configured
if [ -n "$LITESTREAM_REPLICA_URL" ]; then
    echo "[entrypoint] Restoring database from $LITESTREAM_REPLICA_URL..."
    litestream restore -if-replica-exists -o "$APP_DIR/database/database.sqlite" "$LITESTREAM_REPLICA_URL" \
        && echo "[entrypoint] Database restored." \
        || echo "[entrypoint] No replica found, starting fresh."
fi

# Create SQLite file if it doesn't exist (first run or no Litestream)
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

# Always sync feature flags (safe: updates/inserts only)
echo "[entrypoint] Syncing feature flags..."
php "$APP_DIR/artisan" db:seed --class=FeatureFlagSeeder --force

# Warm caches
echo "[entrypoint] Caching config, routes, views..."
php "$APP_DIR/artisan" config:cache
php "$APP_DIR/artisan" route:cache
php "$APP_DIR/artisan" view:cache

echo "[entrypoint] Ready. Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
