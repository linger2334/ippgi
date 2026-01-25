# IPPGI Prices Plugin

WordPress plugin for fetching and caching material price data from external API with scheduled tasks.

## Features

- **Scheduled Tasks**: Automatically fetches price data every hour from 9:00-17:00 daily
- **Smart Caching**: Uses WordPress Transients API for efficient data caching
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

The plugin schedules 9 daily tasks (one for each hour from 9:00-17:00). Each task:

1. **Clears all caches** (price list + all real-time prices)
2. **Fetches price list** from API and caches it
3. **Logs execution** details for debugging

### Caching Strategy

- **Price List**: Cached for 1 hour after fetching
- **Real-time Prices**: Cached for 1 hour, fetched on-demand when frontend requests
- Cache is automatically cleared every hour during business hours (9:00-17:00)

### API Integration

**Price List API:**
- URL: `https://api.rendui.com/v1/jec/rendui/prices/daily`
- Method: GET
- Headers: `userid: 33249`, `referer: https://servicewechat.com/...`

**Real-time Price API:**
- URL: `https://api.rendui.com/v1/jec/rendui/daily/getByProductSpecAndDate`
- Method: POST
- Headers: `phone: 18210056805`
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
IPPGI Prices: Starting scheduled task at 2026-01-23 09:00:00 (hour: 9)
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
- Scheduled hourly tasks (9:00-17:00)
- Price list and real-time price caching
- REST API endpoints
- Admin tools for cache management
