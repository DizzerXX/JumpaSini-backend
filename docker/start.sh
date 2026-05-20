#!/bin/sh
php artisan config:cache
php artisan route:cache
php artisan migrate --force
php artisan db:seed --class=VenueSeeder --force
php artisan db:seed --class=AdminSeeder --force
php -S 0.0.0.0:8080 -t public/
