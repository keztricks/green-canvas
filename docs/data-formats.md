# Data formats

Green Canvas was originally written against one specific council's data shapes (Calderdale's electoral register and FOI UPRN release). This document describes exactly what the importers expect, so you can adapt your council's data — or fork the importers — accordingly.

If your council sends a meaningfully different format and you'd rather extend the code than reshape your CSVs, PRs adding pluggable parsers are very welcome.

---

## 1. Electoral register CSV (`/import` page)

Used by `AddressImportController::store()`. One row per elector. Address rows are deduplicated and counted to produce a `Address` record per unique `(ward, house number, street, postcode)`.

### Expected columns (0-indexed)

| Index | Column | Used? | Notes |
|---|---|---|---|
| 0 | Elector Number Prefix | ✗ | |
| 1 | Elector Number | ✗ | |
| 2 | Elector Number Suffix | ✗ | |
| 3 | Elector Markers | ✗ | |
| 4 | Elector DOB | ✗ | |
| 5 | Elector Surname / Name | ✗ | |
| 6 | **Postcode** | ✓ | Required. Rows with empty postcode are skipped. |
| 7 | **Address1** | ✓ | First line of address (typically house number). |
| 8 | **Address2** | ✓ | |
| 9 | **Address3** | ✓ | |
| 10 | **Address4** | ✓ | |
| 11 | **Address5** | ✓ | |
| 12 | **Address6** | ✓ | Often the town/city. |

The first row of the file is treated as a header and skipped. Rows with fewer than 10 columns are skipped (`MIN_COLUMNS` constant in the controller).

The address fields are concatenated and parsed with a small heuristic — the importer looks for a recognised street suffix (`Road`, `Street`, `Lane`, …) to identify the street name, and treats fields before it as the house number and fields after it as the town. Town fields matching configured `town_aliases` are skipped.

### Sample

A 5-row synthetic sample lives at [`docs/sample-data/electoral-register-sample.csv`](sample-data/electoral-register-sample.csv).

### Skip reasons

The success message after import surfaces a per-reason count of skipped rows:

- `too few columns` — row had fewer than 10 fields
- `missing postcode` — column 6 was blank
- `street unparseable` — couldn't identify a street name from the address fields
- `no house number` — street was matched via reference but no number could be extracted

If you see a high count for a category, it usually means your CSV's column order doesn't match. Edit the `COL_*` constants at the top of `AddressImportController` to remap.

---

## 2. Per-ward street-reference CSVs (optional)

Used by `AddressImportController::loadStreetReference()`. These give the importer a definitive `postcode → street name` map for a ward, which short-circuits the heuristic parser. Useful when the electoral-register address fields are inconsistent or when the same postcode covers multiple streets across ward boundaries.

### File location

Stored under `storage/app/<canvassing.ward_reference_dir>/<slug>.csv`, where `<slug>` is `Str::slug($wardName)`. So a ward called "Halifax Town" looks for `halifax-town.csv`. Files are optional — wards without a reference fall back to the heuristic.

### Expected shape

The importer skips the first 14 lines (which Calderdale's FOI release uses for header/preamble) and reads:

| Index | Column | Used? |
|---|---|---|
| 2 | Street | ✓ |
| 8 | Post Code | ✓ |

Other columns are ignored.

### Sample

[`docs/sample-data/ward-reference-sample.csv`](sample-data/ward-reference-sample.csv).

### Adapting for your council

If your council's FOI release has a different number of preamble rows, edit `loadStreetReference()` in the controller (the `for ($i = 0; $i < 14; $i++)` loop). If the column positions differ, edit `$row[2]` / `$row[8]`. We'd accept a PR moving these to constants or to config.

---

## 3. Council UPRN/address CSV (for `addresses:build-uprn-data`)

Used by `BuildUprnData::handle()` to merge a council's UPRN/address mapping with [OS Open UPRN](https://www.ordnancesurvey.co.uk/products/os-open-uprn) coordinates, producing a single CSV that `addresses:geocode-uprn` consumes.

### How to obtain

This is council-specific. In Calderdale's case it came from a Council Tax FOI request — many councils will release the same data under similar wording. Look for "Council Tax property list with UPRN" or "non-personal property data with UPRN".

### Expected columns

The command tries to auto-detect, but works best with:

- a `UPRN` column (any case)
- one or more address-line columns
- a `Postcode` column

See `app/Console/Commands/BuildUprnData.php` for the exact detection logic.

### OS Open UPRN file

Downloaded from [OS Open UPRN](https://www.ordnancesurvey.co.uk/products/os-open-uprn). Standardised CSV with `UPRN, X_COORDINATE, Y_COORDINATE, LATITUDE, LONGITUDE` columns. The importer reads `UPRN`, `LATITUDE`, `LONGITUDE`.

---

## 4. Ward boundaries GeoJSON

Used by `CanvassingController::wardBoundaries()` to draw outlines on the map.

### File location

`storage/app/<canvassing.ward_boundary_file>` (defaults to `ward-boundaries/default.geojson`). Set `CANVASSING_WARD_BOUNDARY_FILE` in `.env` to point at your council's file.

### Expected shape

A GeoJSON `FeatureCollection`. Each feature should have a `properties.name` (or similar) field that matches your `Ward.name` values; the map view uses this to identify which feature corresponds to the active ward.

### How to get one

[OS Boundary-Line](https://www.ordnancesurvey.co.uk/products/boundary-line) is free under OGL v3 and includes all UK council ward boundaries. Download as TAB or SHP, then convert to GeoJSON using QGIS, [mapshaper](https://mapshaper.org), or `ogr2ogr`. Filter to your council's wards before saving — the full GB file is huge.
