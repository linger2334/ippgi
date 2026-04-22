# IPPGI Project Documentation

This project is a custom WordPress-based platform for displaying and managing raw material prices (iPPGI). It features real-time price tracking, historical data analysis, and a membership-based subscription system.

## Project Overview

- **Core Technologies:** WordPress, PHP 8.1, MySQL (custom tables), JavaScript.
- **Architecture:**
  - **Theme:** `wp-content/themes/ippgi` - Handles the frontend UI, responsive design, and membership integration.
  - **Plugin:** `wp-content/plugins/ippgi-prices` - Handles core business logic, including price collection, caching, REST API endpoints, and scheduled tasks.
  - **Database:** Uses custom tables with the `ippgi_` prefix (e.g., `ippgi_prices_gi`, `ippgi_prices_exchange_rates`).
- **External Integrations:**
  - Price APIs from `www.rendui.com/api`.
  - Currency conversion APIs via Aliyun Market.
  - Membership via Simple Membership Plugin (SWPM).
  - Payments via PayPal and Stripe.
  - **Email Delivery:** WP Mail SMTP via Gmail API (OAuth 2.0).

## Operational Facts

- **Primary login route:** `/login/` is the canonical login page. `/membership-login/` remains for backward compatibility only.
- **Logged-in redirect rule:** Logged-in users visiting `/login/` or `/membership-login/` should be redirected to the home page through centralized theme logic.
- **Membership levels in active use:** The project only uses SWPM `Basic (2)` and `Plus (4)` for live membership state. Trial `Level 3` has been retired from business logic, and all temporary gifted access is handled through the bonus meta mechanism.
- **Rendui header rules:**
  - `prices/daily` should not send custom headers.
  - `daily/getByProductSpecAndDate` and `prices/statistics` require `phone: 13792171909`.
- **Membership mail responsibilities:**
  - SWPM auto-sends `Registration Complete`, `Account Upgrade Notification`, and `Subscription Payment Canceled or Expired` emails.
  - Custom theme code handles the payment-success modal and cancellation-state cleanup only; it no longer sends delayed cancellation/expiration emails from the nightly downgrade cron path.
  - For `Account Upgrade Notification`, the current theme customization rewrites the recipient to the email stored on the SWPM member profile before the mail is sent, instead of relying on the payment gateway callback email.
  - The nightly downgrade cron only handles expiry checks, downgrade, bonus activation, and stale cancellation-meta cleanup; it does not send cancellation emails.
- **Translation loading timing:** On WordPress 6.7+, both the plugin and theme should load their textdomains explicitly at the proper hook timing (`init` for plugin, `after_setup_theme` for theme). The `ippgi-prices` scheduler also guards its `cron_schedules` label to avoid triggering translations before `init`.
- **PHP 8.1 compatibility note for SWPM:** `simple-membership/classes/class.swpm-utils-misc.php::get_countries_dropdown()` has a local compatibility patch that normalizes `null` country values to empty strings before calling `strtolower()`, preventing deprecated notices when profile/admin country fields are empty.
- **Homepage middle banner rendering:** The homepage carousel banner should fill the available container width and preserve the source image aspect ratio with `height: auto`; do not reintroduce fixed heights or `object-fit: cover` cropping. The currently uploaded banner images are roughly `485/486 x 120`, so larger desktop layouts may benefit from higher-resolution replacements later.

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
- **`backfill-aliyun-rates-and-reprice.php`**: Legacy maintenance script that is now intentionally disabled because historical rows no longer retain RMB source columns for safe repricing.
- **`resource/` & `screenshot/`**: Design assets, requirements, and UI references.

## Scheduled Tasks (WP-Cron)

- **00:10 (UTC+8):** Save the historical price snapshot and exchange-rate snapshot from the existing cached business-date price list directly into the database. This task does not refresh the Aliyun exchange rate and does not reprice cached USD values. If the cache is missing, it may fall back to the latest upstream price list.
- **01:10 - 08:10 and 18:10 - 23:10 (UTC+8):** Removed. No off-hours FX refresh or cached USD repricing runs anymore.
- **09:10 - 17:10 (UTC+8):** Hourly price refresh. Uses an incremental strategy: keep existing category caches, fetch each category one by one, and preserve the previous USD cache for any category whose upstream fetch fails.

## Cache Strategy

- **Price List Cache:** No longer stored as one large transient. It is split by category using keys like `ippgi_prices_price_list_category_ppgi`, plus a lightweight metadata transient `ippgi_prices_price_list_meta`.
- **Single-spec Detail Cache:** Latest detail payloads are cached separately and are no longer repriced by midnight/off-hours background jobs.
- **REST Compatibility:** The `/prices` endpoint still returns the same overall structure; the server assembles the full response from per-category caches.
- **Reason for Split Cache:** This avoids oversized transient payloads that can fail to persist when the serialized value approaches MySQL packet limits.

## Exchange Rate Source

- **Unified Source:** Current exchange-rate fetching is standardized on the Aliyun Market API.
- **Historical Backfill:** Historical price rows no longer retain RMB source columns. If history needs repair, re-import the affected date range from the source API instead of trying to reprice stored rows from historical FX alone.
