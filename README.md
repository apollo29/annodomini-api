# Anno Domini Game API

REST API for the [Anno Domini](https://annodomini.app) mobile card game. Built with PHP 8.2+ and Slim 4.

## Requirements

- PHP 8.2+
- MySQL 5.7+
- Composer

## Setup

```bash
# Install dependencies
composer install

# Configure environment (gitignored)
cp config/env.php.example config/env.php
# Edit config/env.php: database credentials, API key

# Or set API key via environment variable
export API_KEY="your-api-key"

# Start development server
composer start
# -> http://localhost:8080
```

### Configuration

| File | Purpose |
|------|---------|
| `config/defaults.php` | Default settings (timezone, logging, rate limits, CORS) |
| `config/env.php` | Database, API key, JWT keys (gitignored) |
| `config/local.dev.php` | Development overrides |
| `config/local.prod.php` | Production overrides |
| `config/local.test.php` | Test overrides |

## API v6 Endpoints

All endpoints require `Authorization: Bearer <api-key>`.

### Sync

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/v6/sync?since=YYYYMMDD` | Returns all updates and removals since given date |
| `GET` | `/v6/sync?since=0&only=sets,cards` | Filtered sync (comma-separated types) |

Response:
```json
{
  "timestamp": 20260413,
  "update_types": [{"type": "sets", "date": 20241114}],
  "updates": {
    "sets": [], "cards": [], "opponents": [], "skills": [],
    "virtual_sets": [], "available_sets": [], "virtual_cards": []
  },
  "removals": {}
}
```

### Game

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/v6/game` | Create game (`{"player_id": "..."}`) -> game_id |
| `GET` | `/v6/game/{game_id}` | Get game details |
| `GET` | `/v6/game/expired` | List expired games |
| `DELETE` | `/v6/game/{game_id}?player_id=...` | Delete game |

### Review

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/v6/review/cards` | Submit card review |

### Health

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/ping` | Health check |

## Middleware

1. **ExceptionMiddleware** - JSON error responses, logging
2. **RateLimitMiddleware** - 60 req/min per IP (configurable)
3. **CorsMiddleware** - CORS headers, OPTIONS preflight
4. **SecurityHeadersMiddleware** - HSTS, CSP, X-Frame-Options, X-Content-Type-Options
5. **ApiKeyMiddleware** - timing-safe API key validation

## Development

```bash
composer test          # PHPUnit tests
composer test:coverage # Tests with coverage report
composer cs:check      # Code style check
composer cs:fix        # Auto-fix code style
composer stan          # PHPStan static analysis
composer test:all      # All checks
```

## Architecture

```
src/
  Action/       # Single-action HTTP controllers
  Domain/       # Business logic and services
  Middleware/   # PSR-15 middleware (auth, CORS, rate limit, security)
  Renderer/     # JSON response renderer
  Support/      # Auth utilities (ApiKey, JWT)
config/         # DI container, routes, settings
public/         # Web root (index.php, .htaccess)
tests/          # PHPUnit tests
database/       # SQL schema
```

## License

MIT
