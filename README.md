# JumpaSini Backend API

## Overview

Laravel 11 REST API powering the **JumpaSini** meetup optimizer app. Handles all business logic, Google Maps integration, and data persistence. Frontend clients (Flutter Android, Angular web) never call external APIs directly — all third-party calls are proxied through this backend.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language / Framework | PHP 8.4, Laravel 11 |
| Database | MySQL (via XAMPP for local dev) |
| Maps & Places | Google Maps Platform (Geocoding, Distance Matrix, Places API) |
| Weather | OpenWeatherMap API (Module 6) |
| Cache | Laravel Cache — Redis-ready, file driver for local dev |
| Testing | PHPUnit via `php artisan test` |

---

## Architecture

The codebase follows strict separation of concerns:

- **Repository pattern** — all database access is behind repository interfaces (`app/Repositories/Contracts/`). Controllers and services never call Eloquent directly.
- **Service layer** — business logic lives in `app/Services/`. Services orchestrate repositories, external API clients, and calculation helpers.
- **Thin controllers** — controllers validate input (via Form Requests), call one service method, and return a JSON response via `ApiResponseTrait`.
- **`ApiResponseTrait`** — every response is wrapped in a consistent envelope:
  ```json
  { "success": true, "data": { ... }, "message": "..." }
  ```
- **UUID primary keys** — all models use `HasUuids`; no auto-increment IDs are exposed.

---

## Modules Built

| # | Module | Status |
|---|---|---|
| 1 | Session + Location Input | ✅ Complete |
| 2 | Midpoint Calculation Engine | ✅ Complete |
| 3 | Venue Discovery + Halal Filter | ✅ Complete |
| 4 | Results & Recommendation | ✅ Complete |
| 5 | Collaborative Planning + Shareable Link | ✅ Complete |
| 6 | Weather Awareness | Planned |
| 7 | Admin Panel | Planned |

---

## API Endpoints

All routes are prefixed `/api/v1/`.

### Sessions

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/sessions` | Create a new session |
| `GET` | `/sessions/{short_code}` | Get session by short code |
| `POST` | `/sessions/{short_code}/participants` | Add a participant + location |
| `DELETE` | `/sessions/{short_code}/participants/{id}` | Remove a participant |
| `POST` | `/sessions/{short_code}/midpoint` | Calculate geographic midpoint |
| `GET` | `/sessions/{short_code}/venues` | Get discovered venues near midpoint |
| `GET` | `/sessions/{short_code}/recommendation` | Get best-pick recommendation |
| `GET` | `/sessions/{short_code}/summary` | Full session summary (live + completed) |

### Collaboration

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/sessions/{short_code}/votes` | Cast or update a vote on a venue |
| `GET` | `/sessions/{short_code}/votes` | Get current vote tally |
| `POST` | `/sessions/{short_code}/confirm` | Confirm a venue and close the session |

### Venues

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/venues/{place_id}/halal-report` | Submit a halal status report for a venue |

---

## Local Setup

```bash
# 1. Clone the repo
git clone <repo-url>
cd JumpaSini-Backend

# 2. Install PHP dependencies
composer install

# 3. Create environment file
cp .env.example .env

# 4. Generate app key
php artisan key:generate

# 5. Create the MySQL database (XAMPP / phpMyAdmin or CLI)
#    CREATE DATABASE jumpa_sini;

# 6. Configure database in .env
#    DB_DATABASE=jumpa_sini
#    DB_USERNAME=root
#    DB_PASSWORD=

# 7. Add API keys (see Environment Variables section below)

# 8. Run migrations
php artisan migrate

# 9. Seed the venue database (29 real KL/Selangor venues)
php artisan db:seed --class=VenueSeeder

# 10. Start the dev server
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`.

---

## Environment Variables

Copy `.env.example` to `.env` and fill in the values below.

| Variable | Required | Description |
|---|---|---|
| `APP_URL` | Yes | Base URL of this API server (e.g. `http://localhost:8000`) |
| `FRONTEND_URL` | Yes | URL of the Angular frontend (used to build shareable links) |
| `DB_CONNECTION` | Yes | `mysql` |
| `DB_HOST` | Yes | Database host (e.g. `127.0.0.1`) |
| `DB_PORT` | Yes | Database port (default `3306`) |
| `DB_DATABASE` | Yes | Database name (`jumpa_sini`) |
| `DB_USERNAME` | Yes | Database username |
| `DB_PASSWORD` | Yes | Database password |
| `GOOGLE_MAPS_API_KEY` | **Required** | Enables Distance Matrix, Places, and Geocoding APIs |
| `OPENWEATHERMAP_API_KEY` | Required for Module 6 | Weather data for venue recommendations |
| `MIDPOINT_MAX_IMBALANCE_MINUTES` | No | Max allowed travel-time imbalance in minutes (default: `15`) |
| `MIDPOINT_MAX_ADJUSTMENT_ITERATIONS` | No | Fairness iteration cap for midpoint adjustment (default: `5`) |

---

## Running Tests

```bash
php artisan test
```

Current coverage: **43 tests, 198 assertions** — all passing.

Tests are located in `tests/Feature/Api/V1/` and cover endpoint contracts, validation rules, business logic, and edge cases.

---

## Folder Structure

```
app/
  Http/
    Controllers/Api/V1/   Versioned API controllers (thin — delegate to services)
    Requests/             Form Request validation classes
    Resources/            Laravel API Resources (response shaping)
  Services/
    GoogleMaps/           Google Maps API client (Geocoding, Distance Matrix, Places)
    Midpoint/             Haversine midpoint + fairness adjustment engine
    Venue/                Discovery, filtering, best-pick, recommendation logic
    Session/              Summary builder
  Repositories/
    Contracts/            Repository interfaces
    *.php                 Eloquent-backed implementations
  Models/                 Eloquent models (all with HasUuids)
  Exceptions/             Custom exceptions (GoogleMapsException, etc.)
  Helpers/
    GeoHelper.php         Haversine distance formula
  Traits/
    ApiResponseTrait.php  Consistent JSON envelope for all responses

database/
  migrations/             All schema migrations
  seeders/
    VenueSeeder.php       29 real KL/Selangor venues with coordinates + halal flags

routes/
  api.php                 All API routes (versioned under /api/v1)

tests/
  Feature/Api/V1/         Feature tests for all endpoints
```

---

## MVP Scope

The MVP is scoped to **Klang Valley (KL + Selangor)** only.

| Boundary | Min | Max |
|---|---|---|
| Latitude | 2.6000 | 3.9000 |
| Longitude | 101.2000 | 102.0000 |

Participant addresses outside this bounding box are rejected at the API level with a `422 Unprocessable Entity` response.

---

## Related Repos

| Repo | Stack | Purpose |
|---|---|---|
| `JumpaSini-Mobile-App` | Flutter (Android) | Primary consumer app — session creation and voting |
| `JumpaSini-Frontend-Dashboard` | Angular 17+ | Web shareable-link landing page and session view |
