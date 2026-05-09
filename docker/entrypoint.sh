#!/bin/sh
set -e

mkdir -p /var/run/nginx
mkdir -p /tmp/nginx/client_body
chmod 1777 /tmp/nginx/client_body

echo "Clearing compiled and cached files..."
php artisan clear-compiled
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Running Laravel optimizations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
exec nginx -g "daemon off;"
