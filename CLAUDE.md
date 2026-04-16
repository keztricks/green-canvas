# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Green Canvas** is a political canvassing management web app built with Laravel 12. Campaign teams use it to track door-to-door canvassing across geographic wards, recording voter responses (knock results) and managing election tracking.

## Commands

```bash
# First-time setup
composer setup          # install deps, generate key, migrate, npm install, build assets

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

## Key Conventions

- **Email storage**: always lowercased via `User::setEmailAttribute()` mutator
- **Ward-scoped queries**: always filter through `hasAccessToWard()` before returning data
- **Sort order**: addresses are ordered by `sort_order` column (set at import time), not alphabetically
- **Database**: SQLite by default (`database/database.sqlite`); session, cache, and queue all use the database driver
- **Frontend**: Alpine.js for interactivity, Tailwind CSS for styling — no separate SPA, all server-rendered Blade
