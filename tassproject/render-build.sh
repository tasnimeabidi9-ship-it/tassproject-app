#!/bin/bash
set -e

# Build Angular frontend
echo "Building Angular frontend..."
cd /app/frontend-build
npm ci --prefer-offline 2>/dev/null || npm install
npx ng build --configuration production
mkdir -p /app/public
cp -r dist/frontend/browser/* /app/public/

# Laravel setup
cd /app
php artisan key:generate --force
php artisan migrate --force 2>/dev/null || true
php artisan db:seed --class=UserSeeder --force 2>/dev/null || true
php artisan db:seed --class=DemoDataSeeder --force 2>/dev/null || true

# Clean up frontend source to save memory
rm -rf /app/frontend-build

echo "Starting server..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
