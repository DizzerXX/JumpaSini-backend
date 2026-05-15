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

- PHP 8.x / Laravel 10+
- MySQL database
- Redis for caching (or file cache in local dev)
