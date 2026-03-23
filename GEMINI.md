# IPPGI Project Documentation

This project is a custom WordPress-based platform for displaying and managing raw material prices (iPPGI). It features real-time price tracking, historical data analysis, and a membership-based subscription system.

## Project Overview

- **Core Technologies:** WordPress, PHP 8.1, MySQL (custom tables), JavaScript.
- **Architecture:**
  - **Theme:** `wp-content/themes/ippgi` - Handles the frontend UI, responsive design, and membership integration.
  - **Plugin:** `wp-content/plugins/ippgi-prices` - Handles core business logic, including price collection, caching, REST API endpoints, and scheduled tasks.
  - **Database:** Uses custom tables with the `ippgi_` prefix (e.g., `ippgi_prices_gi`, `ippgi_prices_exchange_rates`).
- **External Integrations:**
  - Price APIs from `api.rendui.com`.
  - Currency conversion APIs via Aliyun Market.
  - Membership via Simple Membership Plugin (SWPM).
  - Payments via PayPal and Stripe.
  - **Email Delivery:** WP Mail SMTP via Gmail API (OAuth 2.0).

## Building and Running

### Requirements
- PHP 8.1+
- MySQL 5.7+
- WordPress 6.x

### Setup
1.  **Plugins:** Ensure `ippgi-prices`, `simple-membership`, and `wp-mail-smtp` are active.
2.  **Theme:** Ensure `ippgi` is the active theme.
3.  **WP-Cron:** Configure a system crontab to run `wp-cron.php` every minute as the `www-data` user. (Crucial for correct file permissions).
    ```bash
    * * * * * cd /path/to/ippgi && /usr/bin/php wp-cron.php >> /var/log/wp-cron.log 2>&1
    ```
4.  **Timezone:** The project logic is centered around the **Asia/Shanghai (UTC+8)** timezone.

### Development Mode
Toggle `IPPGI_DEV_MODE` in `wp-content/themes/ippgi/functions.php` to simulate premium membership status for local testing.

## Development Conventions

- **Source of Truth:** The running code is the primary source of truth. Documentation (`CLAUDE.md`, `AGENTS.md`) should be kept in sync.
- **Modifications:** 
  - Never modify WordPress Core (`wp-admin`, `wp-includes`, root `wp-*.php`).
  - All custom business logic should reside in the `ippgi` theme or `ippgi-prices` plugin.
- **Naming:** 
  - Use the `ippgi_` prefix for all custom database tables, functions, and hooks.
  - REST API namespace: `ippgi-prices/v1`.
- **Validation:** Always sanitize and validate input parameters. Check nonces and capabilities for sensitive operations.
- **Linting:** Use `php -l` to check PHP syntax before committing.

## Key Files and Directories

- **`CLAUDE.md`**: The master project overview, feature list, and development history. **Read this first for context.**
- **`AGENTS.md`**: Guidelines and constraints for AI agents working on this codebase.
- **`wp-content/themes/ippgi/functions.php`**: Main theme initialization and membership logic.
- **`wp-content/plugins/ippgi-prices/ippgi-prices.php`**: Main plugin entry point.
- **`wp-content/plugins/ippgi-prices/includes/`**: Core logic classes (API client, database, scheduler, etc.).
- **`wp-content/plugins/wp-mail-smtp/`**: Handles email delivery via Gmail API.
- **`collect-current-prices.php`**: Root script for manual/scheduled price collection.
- **`import-historical-data.php`**: Maintenance script for populating historical records.
- **`backfill-aliyun-rates-and-reprice.php`**: Maintenance script for backfilling Aliyun exchange rates and repricing historical database records.
- **`resource/` & `screenshot/`**: Design assets, requirements, and UI references.

## Scheduled Tasks (WP-Cron)

- **00:10 (UTC+8):** Refresh the latest Aliyun exchange rate, reprice all cached price-list data and cached single-spec detail data with the newest FX rate, then save the price snapshot and exchange-rate snapshot into the database. RMB values stay unchanged; only USD values are recalculated.
- **01:10 - 08:10 and 18:10 - 23:10 (UTC+8):** Hourly FX-only repricing. These jobs refresh the latest Aliyun exchange rate and reprice both cache layers in memory/transients without collecting fresh market prices.
- **09:10 - 17:10 (UTC+8):** Hourly price refresh. Uses an incremental strategy: keep existing category caches, fetch each category one by one, preserve old category data when the upstream price API fails, and still force USD repricing by the newest FX rate so the whole site stays on one exchange-rate basis.

## Cache Strategy

- **Price List Cache:** No longer stored as one large transient. It is split by category using keys like `ippgi_prices_price_list_category_ppgi`, plus a lightweight metadata transient `ippgi_prices_price_list_meta`.
- **Single-spec Detail Cache:** Latest detail payloads are cached separately and are also repriced during the midnight and off-hours FX refresh jobs.
- **REST Compatibility:** The `/prices` endpoint still returns the same overall structure; the server assembles the full response from per-category caches.
- **Reason for Split Cache:** This avoids oversized transient payloads that can fail to persist when the serialized value approaches MySQL packet limits.

## Exchange Rate Source

- **Unified Source:** Current exchange-rate fetching is standardized on the Aliyun Market API.
- **Historical Backfill:** When historical FX data must be repaired or unified, use `backfill-aliyun-rates-and-reprice.php` to fetch historical Aliyun rates, update the exchange-rate table, and recalculate stored historical USD price fields from preserved RMB values.
