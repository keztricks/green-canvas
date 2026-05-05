# Green Canvas

A self-hosted door-to-door canvassing management app for local Green Party branches. Built for, and in production with, [Calderdale Greens](https://calderdale.greenparty.org.uk).

[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com) [![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://www.php.net) [![AGPL-3.0](https://img.shields.io/badge/Licence-AGPL--3.0-blue)](LICENSE)

---

## What it does

- **Browse by ward → street → address.** Canvassers see only the wards they're assigned to; ward admins see their patch; admins see everything.
- **Log knock results.** Party preference, turnout likelihood, vote likelihood, free-text notes. Multiple knocks per address with full history.
- **Track elections.** Local, general, and by-elections. Per-address voting status (voted / not voted / unknown) so you know who to chase for postal-vote follow-up.
- **Map view.** Every address as a dot, coloured by supporter / party / likelihood / coverage / support. Filterable legend, ward boundaries, optional cross-ward "all wards" mode. Property-precise pins where UPRN data is available, postcode-fanned otherwise.
- **Exports.** Excel files for paper canvassing or reporting. Scheduled per ward per user.
- **Roles.** Admin, ward admin, canvasser. Ward-scoping enforced both in middleware and in business logic.

## Who this is for

Local Green Party branches running canvassing for **a single council**. Specifically: a tech-comfortable organiser, or a developer helping their local branch.

It is **not**:

- A hosted service (you run it yourself).
- A national database (each branch runs their own deployment with their own data).
- A general-purpose CRM (it's narrowly built around UK electoral-register and ward workflows).

If your branch covers multiple councils today, you currently run multiple instances. Multi-council and multi-constituency support are on the roadmap — see [Known limitations](#known-limitations).

## Status & origin

In production with Calderdale Greens since early 2026, refined over multiple election cycles. Open-sourced so other branches can use, fork, or send improvements back.

Built with heavy use of AI-assisted development by a former senior software engineer. That's a deliberate choice — most of this codebase exists because the maintainer could move quickly with AI as a junior pair, not in spite of it. Code is reviewed and maintained by a human who reads it. If you spot something that looks AI-generated and wrong, please open an issue rather than letting it slide.

## Requirements

- PHP 8.2+
- Composer
- Node 20+
- SQLite (default) or MySQL/Postgres if you have particular ops preferences
- ~500 MB disk if you're storing the merged UPRN dataset for a council

## Quick start (local development)

```bash
git clone <this repo> green-canvas && cd green-canvas
composer setup                          # install + migrate + build assets
php artisan canvassing:create-admin     # interactive — create your first user
composer dev                            # Laravel server + queue + log viewer + Vite HMR
```

Visit `http://localhost:8000` and log in as the admin user you just created. The app will be empty — see the next section for getting real data in.

To populate with synthetic demo data instead (useful for development):

```bash
php artisan db:seed
```

Note: the seeder creates demo accounts with the password `password`. Don't run it on a deployment that's going to hold real data.

## Setting up a real branch

Three things you need: an electoral register, ward boundaries, and (ideally) a council UPRN/address mapping for property-precise pins.

### 1. Per-deployment configuration

Edit `.env`:

```bash
APP_NAME="Calderdale Greens"
CANVASSING_BRANCH_NAME="Calderdale Greens"
CANVASSING_COUNCIL_NAME="Calderdale"
CANVASSING_DEFAULT_CONSTITUENCY="Halifax"
CANVASSING_WARD_BOUNDARY_FILE="ward-boundaries/calderdale.geojson"
CANVASSING_TOWN_ALIASES="Halifax,Sowerby Bridge,Hebden Bridge,Todmorden,Brighouse"
```

All settings live in [config/canvassing.php](config/canvassing.php) with explanations.

### 2. Electoral register

Your ERO (Electoral Registration Officer) — usually based at your council — can supply the full electoral register to political parties, candidates, and elected representatives under [Section 99 of the Representation of the People Act](https://www.legislation.gov.uk/uksi/2001/341/regulation/99/made). Different roles get different entitlements; your local Green Party agent will know the right wording.

Once you have the CSV, go to `/import` in the app and upload one ward at a time. The expected format is documented in [docs/data-formats.md](docs/data-formats.md) — you may need to reshape your CSV or edit the column constants in [app/Http/Controllers/AddressImportController.php](app/Http/Controllers/AddressImportController.php) if your council's ERO sends a different layout. The success message after each import tells you how many rows were skipped and why, which makes diagnosis straightforward.

### 3. Ward boundaries

Free, OGL v3, available from [OS Boundary-Line](https://www.ordnancesurvey.co.uk/products/boundary-line). Download as TAB or SHP, filter to your council's wards in [QGIS](https://qgis.org) or [mapshaper](https://mapshaper.org), and save as a GeoJSON FeatureCollection at the path you set in `CANVASSING_WARD_BOUNDARY_FILE`. Each feature's `properties.name` should match your `Ward.name` values.

### 4. UPRN-precise geocoding (optional but recommended)

Without UPRN data, all addresses on a postcode share one centroid and the map fans them out in a sunflower spiral. With UPRN data, each address gets a rooftop coordinate.

You need two files:

1. **A council UPRN/address mapping.** FOI your council with wording like "Council Tax property list including UPRN". This is widely released under OGL v3 — see [Calderdale's release](storage/app/uprn-data/calderdale.csv) for a worked example you can attribute when asking.
2. **OS Open UPRN.** Free, [download from OS](https://www.ordnancesurvey.co.uk/products/os-open-uprn).

Then run:

```bash
# Merge once (or whenever the OS release updates)
php artisan addresses:build-uprn-data \
  --council=path/to/council-foi.csv \
  --os-uprn=path/to/osopenuprn_NNNN.csv \
  --output=storage/app/uprn-data/{your-council}.csv

# After each electoral-register import, run UPRN geocoding
php artisan addresses:geocode-uprn --data=storage/app/uprn-data/{your-council}.csv

# Optional postcode-centroid fallback for whatever didn't match
php artisan addresses:geocode

# Anything still without coordinates → admins/ward-admins place by clicking
# on the map (use the "Missing addresses" floating button)
```

Calderdale's merged UPRN dataset is included in the repo under [storage/app/uprn-data/](storage/app/uprn-data/) — both source licences permit redistribution with attribution, which the map already provides.

### 5. Required attribution

If you use OS data, the map's tile-layer attribution **must** include:

- `Contains OS data © Crown copyright and database right [year]`
- `Council data © {Your Council} Council, OGL v3` (set via `CANVASSING_COUNCIL_NAME`)

Both are automatic — don't remove them.

## Deploying

`Dockerfile` and [`update.sh`](update.sh) are present as starting points. For production, you'll want to:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure mail (`MAIL_*`) — scheduled exports go out as email attachments.
- Run a queue worker for export jobs (`php artisan queue:work`).
- Serve over HTTPS.

## Known limitations

These are honest, not hidden:

- **One council, one constituency per deployment.** Branches spanning multiple councils run multiple instances. A `Constituency` model with `wards.constituency_id` is the planned next step — happy to discuss in an issue if your branch needs this.
- **Importers were written against Calderdale's specific CSV shapes.** The expected layouts are documented in [docs/data-formats.md](docs/data-formats.md). If your council's data looks different, you'll either reshape the CSV before import or edit the importer. PRs adding pluggable parsers are very welcome.
- **No Excel import support** — CSV only.
- **Manual geocoding workflow.** After each import you run two artisan commands. A scheduled or auto-trigger flow would be a small improvement.
- **Ward-reference CSV format** assumes 14 preamble lines and specific column positions. Same caveat as above.

## Architecture (briefly)

Server-rendered Laravel 12 + Blade with Alpine.js for interactivity and Tailwind for styling. SQLite by default for sessions/cache/queue. No SPA, no separate frontend repo.

Models, request flow, role enforcement, geocoding pipeline: see [CLAUDE.md](CLAUDE.md) for the full overview.

## Contributing

PRs welcome — please read [CONTRIBUTING.md](CONTRIBUTING.md) first. The most useful contributions are:

- Importer support for other councils' data formats.
- Multi-constituency / multi-council modelling work (open an issue first).
- Accessibility improvements.
- Documentation of how you stood the app up for a council that isn't Calderdale.

## Security

Vulnerability disclosure: see [SECURITY.md](SECURITY.md). Please don't open public issues for security bugs.

## Licence

[AGPL-3.0](LICENSE). Forking and self-hosting is fine; running the app as a service for others requires you to share your modifications back. The licence reflects the project's intent: a tool built collaboratively for political organising, with improvements flowing back to the community rather than into closed forks.

## Acknowledgements

- [Laravel](https://laravel.com), the framework underneath all this.
- [Ordnance Survey](https://www.ordnancesurvey.co.uk) for OS Open UPRN and OS Boundary-Line under the Open Government Licence.
- [postcodes.io](https://postcodes.io) for the postcode-centroid fallback.
- [OpenStreetMap](https://www.openstreetmap.org) contributors for map tiles.
- [Calderdale Council](https://www.calderdale.gov.uk) for FOI release of UPRN/address data under OGL v3.
- [Calderdale Greens](https://calderdale.greenparty.org.uk) for being the test bed.
