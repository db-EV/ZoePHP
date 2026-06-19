# CLAUDE.md — ZoePHP Codebase Guide

This file provides guidance for AI assistants working in this repository.

## Project Overview

ZoePHP is an **unofficial PHP dashboard** for Renault electric vehicles (Zoe Ph1, Zoe Ph2, Megane E-Tech). It fetches live data from Renault's Gigya/Kamereon API and displays battery status, charging state, mileage, GPS location, and weather. It can also send commands (start AC preconditioning, start charging, toggle charging schedule).

Key characteristics:
- Pure PHP with no framework, no Composer, no OOP — procedural throughout
- Works as both a **web application** and a **CLI/cron script**
- PWA-capable (installable as a web app)
- Multi-language: English, Deutsch, Österreichisch, Italiano, Svenska
- Flat-file storage only (JSON session + CSV database) — no database server required

## Repository Structure

```
ZoePHP/
├── src/
│   ├── index.php           # Main entry point and request orchestrator
│   ├── functions.php       # All helper/utility functions (~549 lines)
│   ├── config.php          # User configuration — GITIGNORED, never commit
│   ├── api-keys.php        # Hardcoded Europe-wide Gigya key and Kamereon API key
│   ├── debug.php           # Raw API response viewer for troubleshooting
│   ├── history.php         # Charging history page (Ph2 only)
│   ├── migration.php       # One-time migration from legacy pipe-format to JSON
│   ├── stylesheet.css      # Application CSS
│   ├── zoephpy.webmanifest # PWA manifest
│   ├── .htaccess.example   # Apache access-restriction and rewrite template
│   ├── favicon.ico
│   ├── icon-192x192.png
│   ├── icon-512x512.png
│   ├── lng/
│   │   ├── AT.php          # Austrian German strings
│   │   ├── DE.php          # German strings
│   │   ├── EN.php          # English strings
│   │   ├── IT.php          # Italian strings
│   │   └── SE.php          # Swedish strings
│   └── templates/
│       ├── dashboard.php   # Main dashboard HTML (uses $session, $lng globals)
│       └── history.php     # Charging history HTML
├── README.md
├── CLAUDE.md               # This file
├── LICENSE                 # MIT
└── screenshot_ph1.png / screenshot_ph2.png
```

**Gitignored files** (never commit):
- `src/config.php` — contains Renault credentials
- `src/session` — cached JWT token, account ID, vehicle data
- `src/database.csv` — historical vehicle data log

## Architecture

### Execution Flow

```
index.php
  ├── require api-keys.php   → $gigya_api, $kamereon_api
  ├── require config.php     → $username, $password, $vin, $country, $zoeph, …
  ├── require functions.php  → all helper functions
  ├── require lng/$country.php → $lng (localised strings array)
  │
  ├── Parse commands from $_GET / $argv
  ├── Load session from flat file (sessionLoad)
  ├── Validate CSRF on POST
  ├── Check rate limits and cron intervals
  ├── Authenticate with Gigya if token expired
  ├── Execute vehicle commands (HVAC, charge, schedule)
  ├── Fetch vehicle data (battery first; mileage/charge-mode/GPS in parallel; then weather)
  ├── Trigger notifications (email, shell command)
  ├── Persist data (sessionSave, csvAppend)
  └── require templates/dashboard.php  → HTML output (or plain text for cron)
```

### Routing

Routing is query-parameter-based, not URL-based:

| URL / CLI argument | Effect |
|--------------------|--------|
| `index.php` | Dashboard (web) |
| `index.php?cron` or `php index.php cron` | Cron mode (plain text output) |
| `index.php?acnow` | Start AC preconditioning |
| `index.php?chargenow` | Start instant charging |
| `index.php?cmon` | Enable charging schedule |
| `index.php?cmoff` | Disable charging schedule |
| `debug.php` | Raw API response viewer |
| `history.php` | Charging history (Ph2) |

### State / Persistence

| Store | Format | Path | Purpose |
|-------|--------|------|---------|
| Session | JSON | `src/session` | JWT token, account ID, cached vehicle data, command timestamps |
| Database | CSV (`;` delimiter) | `src/database.csv` | Historical data log (optional) |

All file writes use `flock()` for safe concurrent access.

## Development Setup

1. **Requirements**: PHP 5.3+ with cURL extension; write permissions on `src/`
2. **Configuration**: Copy `src/config.php` from the template in the repo, fill in credentials
3. **Access restriction**: Copy `src/.htaccess.example` to `src/.htaccess` if on Apache
4. **Migration** (upgrades only): Run `php src/migration.php` once to convert old pipe-delimited session format to JSON
5. **No build step** — no Composer, no npm, no compilation

## Running and Testing

There is **no automated test suite**. All testing is manual:

| Method | Command |
|--------|---------|
| Browser | Open `https://your-server/src/index.php` |
| CLI one-shot | `php src/index.php` |
| CLI cron mode | `php src/index.php cron` |
| API debug | Open `src/debug.php` in a browser |
| Cron via curl | `curl -s 'https://your-server/src/index.php?cron'` |

When testing changes locally, run `php src/index.php cron` and verify the plain-text output. Check `src/session` (JSON) to confirm data is being saved correctly.

## Code Conventions

### Style
- **Procedural PHP only** — do not introduce classes or namespaces
- **`snake_case`** for all variable and function names
- **PHPDoc blocks** on every function with `@param` and `@return` annotations
- **Typed parameters and return types** (`string $path`, `: array`) on all functions
- **Section dividers** in `functions.php` using `// ─── Section Name ────────────────────`
- Indentation: 4 spaces (no tabs)

### Dependencies
- **No external libraries** — only PHP built-ins and cURL
- Do not introduce Composer or any third-party packages

### Security (always enforce)
- Escape all output with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` before rendering in templates; add `rel="noopener noreferrer"` to any `target="_blank"` link
- Validate CSRF tokens on every POST request before processing
- Use `escapeshellarg()` / `escapeshellcmd()` for any shell command; never interpolate user data directly
- Set all security headers (`Content-Security-Policy`, `X-Frame-Options`, `Cache-Control: no-store`, etc.) on every web response
- Keep user-facing and cron error messages generic — never echo raw exception text or full request URLs, which can expose the account ID and VIN
- Enforce command cooldowns via `cmdAllowed()` — do not bypass rate limiting

### PHP Compatibility
- Code must remain compatible with PHP 5.3 where possible
- Use `DateTimeImmutable` and `DateTimeZone` for all date/time work
- Throw `RuntimeException` for unrecoverable API errors; callers use try/catch

## Key Functions Reference (`src/functions.php`)

### Session Management
| Function | Purpose |
|----------|---------|
| `sessionDefaults(): array` | Returns the canonical session schema with defaults |
| `sessionLoad(string $path): array` | Loads JSON session file, merges with defaults |
| `sessionSave(string $path, array $session): bool` | Writes session to file with locking; returns false on failure |
| `cmdAllowed(array $session, string $cmd, int $cooldownSec): bool` | Enforces per-command cooldown |

### HTTP Layer
| Function | Purpose |
|----------|---------|
| `curlRequest(string $url, array $options): array` | Low-level cURL wrapper (applies shared timeouts; callers may override via `$options`) |
| `kamereonGet(...)` | GET request with Kamereon auth headers |
| `kamereonGetMulti(array $urls, string $apiKey, string $token): array` | Fetches several Kamereon endpoints concurrently via `curl_multi`; returns `null` per key on failure instead of throwing |
| `kamereonPost(...)` | POST request with JSON body to Kamereon |
| `gigyaPost(...)` | Form-encoded POST to Gigya auth |
| `httpTimeoutOptions(): array` | Shared cURL connect/total timeout options used by every HTTP helper — tune timeouts in this one place |

### Authentication
| Function | Purpose |
|----------|---------|
| `gigyaLogin(string $apiKey, string $user, string $pass): array` | Two-step Gigya login → JWT |
| `fetchAccountId(...): string` | Fetches Kamereon account ID |

### Vehicle Data (read)
| Function | Purpose |
|----------|---------|
| `fetchBatteryStatus(...)` | Battery level, charging status, plug state |
| `fetchCockpit(...)` | Mileage |
| `fetchChargeMode(...)` | Charging schedule state |
| `fetchHvacStatus(...)` | AC preconditioning status |
| `fetchLocation(...)` | GPS coordinates (Ph2 only) |
| `fetchChargingHistory(...)` | Past charging records |

> `index.php` fetches the independent reads — cockpit, charge-mode and (Ph2) location — concurrently through `kamereonGetMulti()`. Battery status runs first because it gates the change-detection hash, and weather runs last because it depends on the GPS coordinates. The single-fetch `fetch*` functions above are still used directly by `debug.php`.

### Vehicle Commands (write)
| Function | Purpose |
|----------|---------|
| `sendHvacStart(...)` | Request AC preconditioning |
| `sendChargingStart(...)` | Request instant charging |
| `sendChargeMode(...)` | Toggle charging schedule on/off |

### Utilities
| Function | Purpose |
|----------|---------|
| `fetchWeather(...)` | OpenWeatherMap lookup (Ph2 only) |
| `execSafe(string $command, string $message): void` | Safe shell execution for notifications |
| `csvAppend(string $path, array $fields, ?array $header = null): bool` | Append row to CSV with locking; returns false on lock/write failure |
| `filePutContentsLocked(string $path, string $content): bool` | Atomic, locked file write; returns false on lock/write failure |
| `parseApiTimestamp(string $isoString, string $timezone): ?DateTimeImmutable` | ISO 8601 → local DateTimeImmutable (or null on failure) |
| `nowStrings(): array` | Current date/time strings (`date_md`, `timestamp_hi`); uses server timezone |

## Configuration Variables (`src/config.php`)

After `require 'config.php'`, these globals are available everywhere:

| Variable | Description |
|----------|-------------|
| `$zoename` | Display name shown in the UI heading |
| `$zoeph` | Model: `1` = Zoe Ph1, `2` = Zoe Ph2 / Megane E-Tech |
| `$username` | My Renault login email |
| `$password` | My Renault password |
| `$vin` | Vehicle Identification Number |
| `$country` | Registration country: `DE`, `AT`, `IT`, `SE`, `GB` |
| `$timezone` | Derived from `$timezones[$country]` |
| `$save_in_db` | `true` to append data to `database.csv` |
| `$cron_ncs` | Cron interval (minutes) when NOT charging |
| `$cron_acs` | Cron interval (minutes) when charging |
| `$mail_bl` / `$exec_bl` / `$cmon_bl` | Actions when battery level threshold is reached |
| `$mail_csf` / `$exec_csf` | Actions when charging finishes |
| `$hide_cm` | Hide charging schedule UI |
| `$map_provider` | `'google'` or `'osm'` for map links (Ph2) |
| `$weather_api_key` | OpenWeatherMap API key (Ph2 only) |
| `$abrp_token` / `$abrp_model` | ABRP live-data integration |

## Session Schema

The `src/session` JSON file has this structure (defined in `sessionDefaults()`):

```json
{
  "token_date": "0000",
  "jwt_token": "",
  "account_id": "",
  "data_hash": "",
  "last_request": "202001010000",
  "bl_action_done": false,
  "is_charging": false,
  "mileage": "",
  "status_date": "", "status_time": "",
  "charging_status": 0, "plug_status": 0,
  "battery_level": 0, "range_km": 0,
  "charging_time": "", "charging_power": 0,
  "gps_lat": "", "gps_lon": "", "gps_date": "", "gps_time": "",
  "notify_bl": 80,
  "temperature": "", "weather": "",
  "charge_mode": "",
  "csrf_token": "",
  "last_cmd": {
    "acnow": 0, "chargenow": 0, "cmon": 0, "cmoff": 0
  }
}
```

Delete `src/session` after every script update to prevent stale data issues.

## API Integration

| Service | Purpose | Authentication |
|---------|---------|----------------|
| Gigya (`accounts.eu1.gigya.com`) | Authentication — login and JWT retrieval | Single Europe-wide API key (`$gigya_api`) |
| Kamereon (`api-wired-prod-1-euw1.wrd-aws.com`) | Vehicle data read/write | JWT token + API key |
| OpenWeatherMap | Current weather at GPS location (Ph2) | API key in config |
| ABRP | Push live vehicle data for route planning | Token in config |

## Git Workflow

- **Main branch**: `master`
- **Feature branches**: prefix `claude/` for AI-generated work (e.g. `claude/fix-something-XxXxX`)
- **Commit messages**: English, imperative mood (`feat:`, `fix:`, `docs:`, `chore:`, `security:`)
- No CI/CD pipeline; no automated lint or test checks

## Supported Vehicles and Countries

| Vehicle | `$zoeph` | Notes |
|---------|----------|-------|
| Renault Zoe Ph1 | `1` | No GPS, no weather |
| Renault Zoe Ph2 | `2` | GPS, weather, history |
| Renault Megane E-Tech | `2` | Same API path as Ph2 |

| Country | Code | Language file |
|---------|------|---------------|
| United Kingdom | `GB` | `lng/EN.php` |
| Germany | `DE` | `lng/DE.php` |
| Austria | `AT` | `lng/AT.php` |
| Italy | `IT` | `lng/IT.php` |
| Sweden | `SE` | `lng/SE.php` |
