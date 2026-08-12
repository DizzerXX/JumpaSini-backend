# JumpaSini Backend — Laravel

This is the Laravel API backend for JumpaSini.

## Responsibilities

- Midpoint calculation between multiple user locations
- Google Places API integration for venue discovery
- Session management for collaborative planning sessions
- Venue caching and halal tagging
- Weather checks via OpenWeatherMap API

## Critical Rule

**All Google API calls must go through this backend.** Never call Google APIs directly from the frontend dashboard or mobile app.

Places, Place Details, and reverse geocoding are exposed to clients via the
`/api/v1/places/*` proxy (see `PlaceController`). Only the Maps **JavaScript/SDK**
key for rendering a map may live in a client, and it must be platform-restricted in
the Google Cloud console. A single key cannot be restricted for browser, Android,
and server at once — issue one key per platform.

## Coding Standards

- Follow **PSR-12** coding standards strictly
- Use the **repository pattern** for all database interactions — no direct Eloquent calls in controllers
- Cache all Places API results for **24 hours** using Laravel Cache (`Cache::remember(...)`)
- API responses must be JSON; use Laravel API Resources for response shaping
- Validation goes in Form Request classes, not controllers

## Project Structure

```
app/
  Http/
    Controllers/    — thin controllers, delegate to services
    Requests/       — form request validation
    Resources/      — API response shaping
  Repositories/     — all DB interactions
  Services/         — business logic (midpoint calc, Places, Weather)
  Models/
routes/
  api.php           — all routes are API routes
```

## Environment

- PHP 8.2+ / Laravel 11
- **PostgreSQL** database (`DB_CONNECTION=pgsql`)
- Cache: `CACHE_STORE=database` (Redis optional; not used on the current Render tier)
- `SESSION_DRIVER=array` — this is a token-auth API. Do **not** set it to `database`:
  the `sessions` table holds meetup sessions, not Laravel's session store.
- Tests run against in-memory SQLite (set in `phpunit.xml`). Keep it that way —
  `RefreshDatabase` drops and re-migrates whatever connection is active, so an
  unset `DB_CONNECTION` would wipe the database configured in `.env`.
- Because tests run on SQLite and production on Postgres, avoid driver-specific SQL
  in repositories (no `SPLIT_PART`, no `::int` casts).
