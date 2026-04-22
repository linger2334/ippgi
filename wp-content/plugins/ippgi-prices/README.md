# IPPGI Prices Plugin

WordPress plugin for fetching and caching material price data from external API with scheduled tasks.

## Features

- **Scheduled Tasks**: Runs hourly workflows at `:10` past each hour, with full price refresh from `09:10-17:10`
- **Smart Caching**: Uses WordPress Transients API with per-category price-list caches to avoid oversized payloads
- **REST API**: Exposes endpoints for frontend consumption
- **Two Data Types**:
  - Price List: Overview of all material prices
  - Real-time Price: Detailed price for specific material (type + width + thickness)

## Installation

1. Upload the `ippgi-prices` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically schedule hourly tasks

## REST API Endpoints

### Get Price List
```
GET /wp-json/ippgi-prices/v1/prices
```

Returns the complete price list for all materials.

**Response:**
```json
{
  "success": true,
  "data": { ... },
  "cached": true
}
```

### Get Real-time Price
```
GET /wp-json/ippgi-prices/v1/price?product_type=PPGI&width=1000&thickness=0.09
```

**Parameters:**
- `product_type` (required): Material type (PPGI, GI, GL, HRC, CRC Hard, AL)
- `width` (required): Width in mm (e.g., 1000, 1200)
- `thickness` (required): Thickness (e.g., 0.09, 0.10)
- `date` (optional): Date in Y-m-d format (defaults to today)

**Response:**
```json
{
  "success": true,
  "data": { ... },
  "cached": false
}
```

### Get Cache Statistics (Admin Only)
```
GET /wp-json/ippgi-prices/v1/cache-stats
```

Returns cache statistics and scheduler information.

### Clear All Caches (Admin Only)
```
POST /wp-json/ippgi-prices/v1/clear-cache
```

Manually clears all cached data.

### Trigger Manual Update (Admin Only)
```
POST /wp-json/ippgi-prices/v1/manual-update
```

Manually triggers the scheduled task (clear cache + fetch price list).

## How It Works

### Scheduled Tasks

The plugin schedules:

- 1 daily snapshot task at `00:10`
- 9 daily business-hour refresh tasks at `09:10-17:10`

Business-hour refresh tasks:

1. **Clears real-time price and exchange-rate caches** (keeps the price list cache)
2. **Fetches price list** from API and caches it
3. **Preserves the previous USD cache** for any category whose upstream fetch fails
4. **Logs execution** details for debugging

Midnight snapshot tasks save the cached business-date price list and its exchange-rate snapshot into the historical tables without refreshing exchange rates and without repricing cached USD values. If the cache is missing, the collector may fall back to the latest upstream price list.

### Caching Strategy

- **Price List**: Stored as per-category transients plus a tiny metadata transient until scheduled workflows replace them
- **Real-time Prices**: Stored in transients and refreshed on-demand when frontend requests miss cache
- **Historical Price Tables**: Persist only `price_usd`, `price_tax_usd`, and `exchange_rate`; RMB source prices are not stored after conversion
- Business-hour refresh runs at `09:10-17:10`; there are no off-hours FX-only repricing tasks

### API Integration

**Price List API:**
- URL: `https://www.rendui.com/api/v1/jec/rendui/prices/daily`
- Method: GET
- Headers: none

**Real-time Price API:**
- URL: `https://www.rendui.com/api/v1/jec/rendui/daily/getByProductSpecAndDate`
- Method: POST
- Headers: `phone: 13792171909`
- Body: `{ productType, width, thickness, date }`

## Frontend Integration

### Example: Fetch Price List

```javascript
fetch('/wp-json/ippgi-prices/v1/prices')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Price list:', data.data);
      console.log('From cache:', data.cached);
    }
  });
```

### Example: Fetch Real-time Price

```javascript
const params = new URLSearchParams({
  product_type: 'PPGI',
  width: 1000,
  thickness: 0.09
});

fetch(`/wp-json/ippgi-prices/v1/price?${params}`)
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Price data:', data.data);
    }
  });
```

## Debugging

### Check Scheduled Tasks

View scheduled tasks in WordPress admin:
- Tools → Site Health → Info → WordPress → Cron

Or use WP-CLI:
```bash
wp cron event list
```

### Check Logs

The plugin logs all scheduled task executions to the WordPress debug log. Enable debugging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `/wp-content/debug.log` for entries like:
```
IPPGI Prices: Starting scheduled task at 2026-01-23 09:10:00 (hour: 9)
IPPGI Prices: Cleared caches - Price list: yes, Real-time prices: 15
IPPGI Prices: Successfully fetched and cached price list
IPPGI Prices: Completed scheduled task in 1.23 seconds
```

### Manual Testing

Use the admin-only endpoints to test:

```bash
# Clear cache
curl -X POST http://yoursite.com/wp-json/ippgi-prices/v1/clear-cache \
  -H "Authorization: Bearer YOUR_TOKEN"

# Trigger manual update
curl -X POST http://yoursite.com/wp-json/ippgi-prices/v1/manual-update \
  -H "Authorization: Bearer YOUR_TOKEN"

# Check cache stats
curl http://yoursite.com/wp-json/ippgi-prices/v1/cache-stats \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Deactivation

When the plugin is deactivated, all scheduled tasks are automatically unscheduled. Cached data remains in the database but will expire after 1 hour.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WP-Cron enabled (or system cron configured)

## Support

For issues or questions, contact the development team.

## Changelog

### 1.0.0
- Initial release
- Scheduled hourly tasks at `:10` past each hour
- Price list and real-time price caching
- REST API endpoints
- Admin tools for cache management
