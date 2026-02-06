#!/bin/bash

# Deployment script for Green Canvas Laravel app
# Usage: ./update.sh [branch/tag]
# Example: ./update.sh master
#          ./update.sh v1.0.0
#          ./update.sh develop

set -e  # Exit on any error

# Get branch/tag from first argument, default to master
BRANCH="${1:-master}"

echo "🚀 Starting deployment..."
echo "📌 Target: $BRANCH"
echo ""
read -p "Continue with deployment? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Deployment cancelled"
    exit 1
fi

# Put application in maintenance mode
echo "📋 Enabling maintenance mode..."
php artisan down

# Fetch latest changes
echo "📥 Fetching latest code..."
git fetch origin

# Checkout and pull the specified branch/tag
echo "🔄 Checking out $BRANCH..."
git checkout "$BRANCH"
git pull origin "$BRANCH"

# Set application version from git tag
echo "📝 Setting application version..."
VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "dev")
if grep -q "^APP_VERSION=" .env; then
    sed -i.bak "s/^APP_VERSION=.*/APP_VERSION=$VERSION/" .env && rm .env.bak
else
    echo "APP_VERSION=$VERSION" >> .env
fi
echo "Version set to: $VERSION"

# Install/update Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install/update NPM dependencies and build assets
echo "🎨 Building frontend assets..."
npm ci
npm run build

# Run database migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Seed feature flags (safe to run, updates existing flags)
echo "🚩 Updating feature flags..."
php artisan db:seed --class=FeatureFlagSeeder --force

# Clear and cache configuration
echo "🧹 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "💾 Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set correct permissions for Apache
echo "🔐 Setting file permissions..."
sudo chown -R apache:apache .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache
sudo chmod -R 664 storage/logs/*.log 2>/dev/null || true

# Disable maintenance mode
echo "✅ Disabling maintenance mode..."
php artisan up

echo "🎉 Deployment complete!"
