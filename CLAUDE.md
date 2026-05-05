# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Green Canvas** is a political canvassing management web app built with Laravel 12. Campaign teams use it to track door-to-door canvassing across geographic wards, recording voter responses (knock results) and managing election tracking.

## Commands

```bash
# First-time setup
composer setup                                # install deps, generate key, migrate, npm install, build assets
php artisan canvassing:create-admin           # interactive — create the first admin user

# Development (runs 4 concurrent processes: Laravel server, queue, Pail log viewer, Vite HMR)
composer dev

# Run all tests
composer test

# Run a single test or filter
php artisan test --filter=CanvassingTest

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Production asset build
npm run build
```

## Architecture

### Request Flow

```
routes/web.php → Controllers → Models (Eloquent) → resources/views/ (Blade + Alpine.js)
```

### Roles & Authorization

Three user roles defined as constants on `User`:
- `ROLE_ADMIN` — full access, including all wards, imports, elections, feature flags
- `ROLE_WARD_ADMIN` — access to assigned wards + export access
- `ROLE_CANVASSER` — access to assigned wards only, no exports

Authorization is enforced at two levels:
1. **Middleware**: `admin` (`IsAdmin.php`) gates the import, feature flag, and election admin routes
2. **Business logic methods**: `User::hasAccessToWard($wardId)` and `User::canAccessExports()` are checked in controllers

Admins bypass ward scoping; ward admins and canvassers are restricted to their `user_ward` pivot assignments.

### Core Domain Models

| Model | Key relationships |
|---|---|
| `User` | belongsToMany `Ward`, hasMany `UserWardExportSchedule` |
| `Ward` | hasMany `Address`, belongsToMany `User`, belongsToMany `Election` |
| `Address` | belongsTo `Ward`, hasMany `KnockResult`, belongsToMany `Election` (pivot: `status`, `notes`) |
| `KnockResult` | belongsTo `Address`, belongsTo `User` |
| `Election` | belongsToMany `Address`, belongsToMany `Ward` |
| `Export` | generated Excel/CSV reports via PhpSpreadsheet |
| `FeatureFlag` | cached runtime toggles (1-hour TTL via Cache facade) |

### Canvassing Flow

The core loop: Ward list → Street list → Address list → log KnockResult. `CanvassingController` handles all these views and the CRUD for knock results. The `Address::scopeByElectionStatus()` scope filters addresses by election vote status using complex `whereHas`/`whereDoesntHave` OR logic.

### KnockResult Response Options

Defined in `KnockResult::responseOptions()` — includes political parties plus `not_home`, `undecided`, `refused`, `wont_vote`, `other`. Turnout likelihood: `wont`, `might`, `will`.

### Export Module

Exports are queued jobs that generate Excel files via `phpoffice/phpspreadsheet`. Results are stored as `Export` model records and downloaded via signed URLs.

### Geocoding

Addresses can be placed on a map at one of three precision levels:

1. **Property-level (preferred)** — via UPRN. Each address is matched to a UPRN from a council-provided address/UPRN mapping (e.g. a Council Tax FOI release under OGL v3), then the UPRN is looked up against [OS Open UPRN](https://www.ordnancesurvey.co.uk/products/os-open-uprn) for rooftop coordinates.
2. **Postcode centroid (fallback)** — via [postcodes.io](https://postcodes.io). All addresses sharing a postcode get the same lat/lng; the map fans them out in a tight sunflower spiral.
3. **Manual** — admins and ward admins can pin un-placed addresses by clicking them on the map (see the "Missing addresses" sheet in the map view).

Required attribution (already in the map's tile-layer attribution):
- `Contains OS data © Crown copyright and database right [year]`
- `Council data © {Council} Council, OGL v3`

#### Runbook

```bash
# 1. One-off per council (or whenever the OS Open UPRN release updates):
#    Merge the council UPRN/address CSV with the OS coordinate file.
#    Output is small (~5MB for Calderdale), licence-safe to commit/distribute.
php artisan addresses:build-uprn-data \
  --council=path/to/council-foi.csv \
  --os-uprn=path/to/osopenuprn_NNNN.csv \
  --output=storage/app/uprn-data/{council}.csv

# 2. After each electoral-register import, run UPRN-based geocoding:
php artisan addresses:geocode-uprn --data=storage/app/uprn-data/{council}.csv

# 3. Optional postcode-centroid fallback for whatever didn't match in step 2:
php artisan addresses:geocode

# 4. Anything still without coordinates → admins/ward-admins place via the
#    "Missing addresses" floating button on the map view.
```

`--ward=N` and `--force` are available on both geocode commands.

The merged UPRN data files in `storage/app/uprn-data/` are committed to the
repository (the OS Open UPRN and Calderdale FOI licences both permit
redistribution with attribution, which the map already provides).

## Per-deployment configuration

`config/canvassing.php` holds per-deployment settings; each is overridable via `.env`:

| Key | Used by | Notes |
|---|---|---|
| `branch_name` | login page heading | e.g. "Calderdale Greens" |
| `credit_line` | layout footers + map loading screen | defaults to "Made in Halifax" |
| `council_name` | map attribution line | omitted when null |
| `default_constituency` | new addresses (import + manual create) | denormalised onto `Address` |
| `ward_boundary_file` | `CanvassingController::wardBoundaries()` | path under `storage/app/` |
| `ward_reference_dir` | `AddressImportController::loadStreetReference()` | files looked up by `Str::slug($wardName).csv` |
| `town_aliases` | importer field-skipping + UPRN matcher noise filter | comma-separated env var |

The model assumes one council and one constituency per deployment. Multi-constituency support (introducing a `Constituency` model with `wards.constituency_id`) is on the roadmap; the README has the user-facing version.

## Key Conventions

- **Email storage**: always lowercased via `User::setEmailAttribute()` mutator
- **Ward-scoped queries**: always filter through `hasAccessToWard()` before returning data
- **Sort order**: addresses are ordered by `sort_order` column (set at import time), not alphabetically
- **Database**: SQLite by default (`database/database.sqlite`); session, cache, and queue all use the database driver
- **Frontend**: Alpine.js for interactivity, Tailwind CSS for styling — no separate SPA, all server-rendered Blade
