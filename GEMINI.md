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
- **`resource/` & `screenshot/`**: Design assets, requirements, and UI references.

## Scheduled Tasks (WP-Cron)

- **00:00 (UTC+8):** Snapshot yesterday's prices and exchange rates into historical tables.
- **09:00 - 17:00 (UTC+8):** Hourly price refresh. Uses an incremental update strategy to preserve cached data if external APIs are unavailable.
