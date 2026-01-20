# Green Canvas - Door-Knocking App

A Laravel backend scaffold for managing door-knocking campaigns. This application helps organize volunteers, manage street assignments, track address visits, and search for addresses with normalization and optional geocoding.

## Features

- **Volunteer Management**: Track volunteers with unique IDs
- **Street Assignment**: Assign streets to volunteers with time-based locks
- **Address Normalization**: Automatic address parsing and normalization for consistent searching
- **Geocoding**: Optional postcode geocoding via postcodes.io API
- **Visit Tracking**: Record and track visits to addresses
- **Fuzzy Search**: Full-text search with fallback to fuzzy matching
- **CSV Import**: Bulk import addresses from CSV files

## Requirements

- PHP ^8.1
- Laravel ^10.0
- MySQL 8.0+
- Composer

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

Copy the example environment file and configure your database:

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and update the database settings:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doorapp
DB_USERNAME=app
DB_PASSWORD=secret
```

### 3. Start Database (Docker)

Use the provided docker-compose.yml to run MySQL locally:

```bash
docker-compose up -d
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Import Addresses

Import addresses from a CSV file:

```bash
# Basic import
php artisan addresses:import path/to/addresses.csv

# With geocoding
php artisan addresses:import path/to/addresses.csv --geocode=true
```

**CSV Format**: The CSV should have a header row and include a column named `address`, `raw_address`, or `Address` containing the full address text.

## API Endpoints

All endpoints are prefixed with `/api`.

### Streets

- **GET /api/streets**
  - List all streets with unvisited address counts
  - Returns: Array of streets ordered by display_name

- **POST /api/streets/{id}/assign**
  - Assign a street to a volunteer
  - Body: `{ "volunteer_id": "uuid", "lock_minutes": 60 }`
  - Returns: 200 on success, 423 if street is locked by another volunteer

- **GET /api/streets/{id}/addresses**
  - Get all addresses for a street
  - Returns: Array of addresses sorted by house number

### Addresses

- **POST /api/addresses/{id}/visit**
  - Record a visit to an address
  - Body: `{ "volunteer_id": "uuid", "status": "contacted", "notes": "..." }`
  - Returns: 200 on success

- **GET /api/addresses/search?q={query}**
  - Search for addresses using normalized text matching
  - Query parameter: `q` (search query)
  - Returns: Top 10 matching addresses ranked by relevance

## Architecture

### Database Schema

- **volunteers**: UUID-based volunteer records
- **streets**: Normalized street names with assignment tracking
- **addresses**: Full address records with normalization and geocoding
- **visits**: Visit history for each address

### Services

- **AddressNormalizer**: Handles address parsing, normalization, and text matching
  - `findPostcode()`: Extract UK postcodes using regex
  - `extractHouseAndStreet()`: Parse house numbers and street names
  - `normText()`: Normalize text for searching (transliteration, lowercase, punctuation removal)
  - `makeKey()`: Generate normalized keys for deduplication

### Commands

- **addresses:import**: Import addresses from CSV with optional geocoding

## Authentication & Authorization

**Note**: The current scaffold accepts `volunteer_id` in request payloads for simplicity. For production:

- Implement Laravel Sanctum or Passport for API authentication
- Replace `volunteer_id` in requests with authenticated user identity
- Add middleware to protect routes and verify user permissions
- Consider role-based access control for admin functions

## Search Implementation

The search functionality uses:

1. **Exact lookup**: Postcode + house number matching for precise queries
2. **Full-text search**: MySQL MATCH AGAINST for natural language matching
3. **Fuzzy fallback**: LIKE queries with Levenshtein distance ranking

### Improving Search

For production workloads, consider:

- MySQL 8 ngram parser for better partial matching
- External search engines (MeiliSearch, Elasticsearch)
- Pre-computed similarity indexes
- Phonetic matching algorithms (Soundex, Metaphone)

## GDPR & Security Considerations

**Important**: This scaffold does not include comprehensive GDPR compliance features. For production:

- ✅ Use HTTPS for all API communication
- ✅ Implement proper authentication and authorization
- ✅ Add data retention policies and automated deletion workflows
- ✅ Implement consent management for data collection
- ✅ Add audit logging for data access and modifications
- ✅ Provide data export functionality for subject access requests
- ✅ Avoid collecting unnecessary sensitive personal information
- ✅ Implement encryption for sensitive fields (if applicable)
- ✅ Regular security audits and dependency updates

## Testing

After PR merge, test the setup:

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Import test data
php artisan addresses:import path/to/test-addresses.csv

# Test API endpoints
curl http://localhost:8000/api/streets
curl http://localhost:8000/api/addresses/search?q=123+Main+Street
```

## License

MIT
