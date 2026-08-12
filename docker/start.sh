#!/bin/sh
# Abort startup on any failure below. Previously a failed migration was ignored and
# the app came up against a half-applied schema, which surfaces as confusing 500s
# rather than a failed deploy.
set -e

# Cache config and routes for the request path. Config is cached BEFORE seeding, so
# anything the seeders need must be read via config() rather than env() — env() is
# not reliable once the config cache is in place.
php artisan config:cache
php artisan route:cache

php artisan migrate --force

# Seeds are idempotent (firstOrCreate). Non-fatal: a seeding problem should not stop
# the service from coming up, unlike a migration problem.
php artisan db:seed --class=VenueSeeder --force || echo "WARN: VenueSeeder failed, continuing."
php artisan db:seed --class=AdminSeeder --force || echo "WARN: AdminSeeder failed, continuing."

# Clear only the runtime cache store — the config/route caches built above are kept.
php artisan cache:clear || true

tail -f storage/logs/laravel.log &
exec php -S 0.0.0.0:8080 -t public/
