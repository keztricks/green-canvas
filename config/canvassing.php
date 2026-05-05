<?php

/*
|--------------------------------------------------------------------------
| Green Canvas — per-deployment configuration
|--------------------------------------------------------------------------
|
| Each deployment of Green Canvas runs for a single party branch covering a
| single council. The values below are the things you'll want to set per
| deployment — branding strings, the council whose data you're using, and
| the local-knowledge hints that help the address importer and geocoder
| make sense of UK address fields.
|
| Override these via the corresponding env vars in .env — see .env.example.
|
*/

return [

    /*
    | The branch name shown on the login page and in page titles
    | (e.g. "Calderdale Greens", "Sheffield Greens").
    */
    'branch_name' => env('CANVASSING_BRANCH_NAME', 'Green Canvas'),

    /*
    | A short credit line shown in the footer and on the map's loading
    | screen. Defaults to "Made in Halifax" (where Green Canvas was
    | built); branches are welcome to change it but please keep some
    | form of credit to the project visible. Set to an empty string
    | to hide the line entirely.
    */
    'credit_line' => env('CANVASSING_CREDIT_LINE', 'Made in Halifax'),

    /*
    | The council whose UPRN/address data you're using. Used in the map
    | attribution to credit the council's open data licence (OGL v3).
    | Set to null to omit the council line — the OS attribution is always
    | shown regardless.
    */
    'council_name' => env('CANVASSING_COUNCIL_NAME'),

    /*
    | Default parliamentary constituency for newly imported addresses.
    | Stored on each address as a denormalised string.
    |
    | Note: the current model assumes one constituency per deployment.
    | Branches spanning multiple constituencies are on the roadmap — see
    | the README for the planned Constituency model.
    */
    'default_constituency' => env('CANVASSING_DEFAULT_CONSTITUENCY'),

    /*
    | Path (relative to storage/app) to a GeoJSON FeatureCollection of
    | ward boundaries for your council. Properties on each feature should
    | include a name field that matches your Ward.name values; the map
    | view uses this to outline wards.
    |
    | OS Boundary-Line is the standard source (free, OGL v3). See README.
    */
    'ward_boundary_file' => env('CANVASSING_WARD_BOUNDARY_FILE', 'ward-boundaries/default.geojson'),

    /*
    | Directory (relative to storage/app) where the importer looks for
    | per-ward street-reference CSVs. Filenames must be the slugified
    | ward name plus .csv (e.g. "Halifax Town" → halifax-town.csv).
    | These are optional — if a ward has no reference file, the importer
    | falls back to parsing addresses from the electoral-register fields.
    */
    'ward_reference_dir' => env('CANVASSING_WARD_REFERENCE_DIR', 'ward-references'),

    /*
    | Comma-separated list of town and locality names used in your
    | council area. The importer uses these to skip town-only fields
    | when parsing electoral-register addresses, and the UPRN matcher
    | uses them as noise tokens when scoring address similarity.
    |
    | Example: "Halifax,Sowerby Bridge,Hebden Bridge,Todmorden,Brighouse"
    */
    'town_aliases' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CANVASSING_TOWN_ALIASES', ''))
    ))),

];
