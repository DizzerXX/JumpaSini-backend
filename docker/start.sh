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

# Retry before giving up: a managed Postgres instance that is cold, waking, or
# briefly unreachable would otherwise fail the whole deploy on a transient error.
# A genuinely misconfigured or suspended database still fails, loudly, after this.
migrate_with_retry() {
  attempt=1
  max_attempts=5
  delay=5

  while [ "$attempt" -le "$max_attempts" ]; do
    if php artisan migrate --force; then
      return 0
    fi

    if [ "$attempt" -eq "$max_attempts" ]; then
      echo "ERROR: migrations failed after ${max_attempts} attempts."
      echo "       Check the database is running and reachable, and that DB_HOST"
      echo "       uses the INTERNAL hostname and DB_SSLMODE is set correctly."
      return 1
    fi

    echo "WARN: migration attempt ${attempt}/${max_attempts} failed; retrying in ${delay}s..."
    sleep "$delay"
    attempt=$((attempt + 1))
    delay=$((delay * 2))
  done
}

migrate_with_retry

# Seeds are idempotent (firstOrCreate). Non-fatal: a seeding problem should not stop
# the service from coming up, unlike a migration problem.
php artisan db:seed --class=VenueSeeder --force || echo "WARN: VenueSeeder failed, continuing."
php artisan db:seed --class=AdminSeeder --force || echo "WARN: AdminSeeder failed, continuing."

# Clear only the runtime cache store — the config/route caches built above are kept.
php artisan cache:clear || true

tail -f storage/logs/laravel.log &
exec php -S 0.0.0.0:8080 -t public/
