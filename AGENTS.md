# AGENTS.md

PSN 100% is a PHP 8.5 + MySQL web application that tracks PlayStation trophies. The
web frontend lives in `wwwroot/` (custom front controller in `wwwroot/index.php`),
domain logic in `wwwroot/classes/`, and a custom test suite in `tests/`.

## Cursor Cloud specific instructions

PHP 8.5 (CLI + `pdo_mysql`, `sqlite3`, `curl`, `gd`, `mbstring`, `intl`, `bcmath`,
`gmp`, `zip`), Composer, and MySQL 8.0 are already installed in the VM image. The
startup update script runs `composer install` for `wwwroot/`. The notes below cover
the non-obvious steps to actually run the services.

### Database (MySQL)

- MySQL does not auto-start. Start it each session with `sudo service mysql start`.
- The app reads its DB connection from **environment variables**: `DB_HOST`,
 `DB_NAME`, `DB_USER`, `DB_PASSWORD` (see `wwwroot/database.php` /
 `ApplicationContainer::create()`). There is no config file fallback.
 `DatabaseConfig::fromEnvironment()` also falls back to `getenv()` when `$_ENV`
 is empty, but missing values still produce a clear connection error.
- IP rate limits use `REMOTE_ADDR` unless `TRUSTED_PROXY_IPS` lists the direct
 client as a trusted proxy, in which case the leftmost valid `X-Forwarded-For`
 address is used.
- Dev database/credentials used during setup: db `psn100`, user `psn100`,
  password `psn100`, host `127.0.0.1`.
- The committed schema is **structure-only** (`database/psn100.sql`, no rows). If the
  `psn100` database is missing on a fresh VM, recreate and (optionally) seed it:
  ```bash
  sudo mysql -e "CREATE DATABASE IF NOT EXISTS psn100 CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
    CREATE USER IF NOT EXISTS 'psn100'@'127.0.0.1' IDENTIFIED BY 'psn100';
    GRANT ALL PRIVILEGES ON psn100.* TO 'psn100'@'127.0.0.1'; FLUSH PRIVILEGES;"
  sudo mysql psn100 < database/psn100.sql
  ```
  The schema imports cleanly on the bundled MySQL 8.0 even though README targets 8.4.

### Web app (PHP built-in server)

- Production uses Apache with `FallbackResource /index.php` (see `wwwroot/.htaccess`).
  The PHP built-in server has no equivalent, so pretty URLs (`/about/`, `/game/1`,
  `/player/<id>`) need a tiny router script that serves real files directly and
  routes everything else through `index.php`.
- `$_ENV` is only populated when `variables_order` includes `E`, which the default
  php.ini does not. Pass `-d variables_order=EGPCS` (or export+`-d`) or the DB env
  vars will not reach the app and the connection will fail.
- Start the dev server from inside `wwwroot/` (relative `require` paths in endpoints
  like `add_to_queue.php` resolve against the cwd):
  ```bash
  # one-time: create the dev router
  cat > /tmp/psn100-router.php <<'PHP'
  <?php
  $docroot = '/workspace/wwwroot';
  $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
  $candidate = realpath($docroot . $uri);
  if ($uri !== '/' && $candidate !== false && is_file($candidate) && str_starts_with($candidate, $docroot . '/')) {
      return false;
  }
  $_SERVER['SCRIPT_NAME'] = '/index.php';
  $_SERVER['SCRIPT_FILENAME'] = $docroot . '/index.php';
  require $docroot . '/index.php';
  PHP

  cd wwwroot && DB_HOST=127.0.0.1 DB_NAME=psn100 DB_USER=psn100 DB_PASSWORD=psn100 \
    php -d variables_order=EGPCS -S 0.0.0.0:8000 /tmp/psn100-router.php
  ```
- The "Update" box on the homepage submits a PSN name to `add_to_queue.php?q=<name>`
  (param key is `q`); valid names must match `PlayerQueueService::ONLINE_ID_PATTERN`
  (`^[a-zA-Z][a-zA-Z0-9_-]{2,15}$` — 3–16 characters, starting with a letter;
  letters, numbers, hyphens, and underscores allowed) and are inserted into
  `player_queue`. This is a quick way to exercise a real DB write.

### `/admin` and `/cron` access control

- Production Apache hosts should copy the templates in `wwwroot/admin/.htaccess.example`
  and `wwwroot/cron/.htaccess.example` to `.htaccess` in those directories (adjust
  `AuthUserFile` and the allowed cron IP before enabling).
- The PHP built-in server does not read `.htaccess`. Cron scripts are still blocked
  over HTTP because `CronCliAccessGuard` runs in `init.php` and `CronJobBootstrapper`.
  Admin is intentionally left reachable in dev for login testing.
- Cron jobs must be run via CLI, e.g. `php wwwroot/cron/hourly.php`. Each script in
  `wwwroot/cron/` loads `cron/bootstrap.php` first to reject HTTP execution.

### Cron job retry loops

Cron jobs are **intentionally written with infinite retry loops** (`while (true)` with
`sleep` / backoff between attempts). The site depends on these jobs eventually
succeeding — player scans, rankings, trophy metadata, and related data go stale or stop
updating if a cron run gives up.

- Do **not** cap retries, add `maxAttempts`, or refactor infinite loops into "fail
  fast" error handling unless explicitly asked. That pattern is deliberate, not a bug.
- Examples: `HourlyCronJob`, `DailyCronJob`, and `WeeklyCronJob` use
  `executeWithRetry()`; `ThirtyMinuteCronJob` and `PlayerRankingUpdater` retry PSN/API
  and database failures indefinitely; `PlayerScanTrophyProgressSynchronizer::retryNotFound()`
  retries 404s from Sony's API.
- Tests such as `WeeklyCronJobTest` and `PlayerRankingUpdaterTest` assert that
  `while (true)` remains and that there is no `maxAttempt` cap — keep those guarantees
  when touching cron code.
- When improving cron code, preserve or strengthen retry behavior (e.g. better logging,
  backoff tuning). Only exit a loop after success or when the work item is definitively
  unrecoverable (e.g. invalid player data), not on transient errors.

- Queue polling and admin login abuse controls use `ip_rate_limit` and
  `admin_login_throttle` tables (see `database/psn100.sql`). Import the schema on
  fresh VMs; existing production DBs need those tables added before deploying Phase 2.

### Tests

- Run the full suite with `php tests/run.php` (custom runner, no PHPUnit). It does not
  need MySQL — DB-backed tests use in-memory SQLite, so `php8.5-sqlite3` must be
  installed (it is, in the image).
- Optional MySQL integration tests are skipped unless `PSN100_INTEGRATION_TEST_DB=1` and
  `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` point at a reachable database.

### Security headers

- `init.php` sends an enforced `Content-Security-Policy` header (via
  `ContentSecurityPolicy`) alongside existing security headers.
- Date localization uses `/js/localized-date-formatter.js`; changelog grouping uses
  `/js/changelog-date-grouping.js`; homepage queue polling uses
  `/js/player-queue-manager.js`; about-page scan log polling uses
  `/js/scan-log-renderer.js`; admin reported-player deletes use
  `/js/admin-report-delete.js`; admin log bulk actions use
  `/js/admin-log-bulk-actions.js`; admin game merge UI uses `/js/admin-merge-form.js`;
  admin game rescan UI uses `/js/admin-rescan-form.js`.
- Player page templates (`player.php`, `player_header.php`, `player_log.php`,
  `player_advisor.php`, `player_random.php`, `player_report.php`) and
  `PlayerHeaderViewModel` use `Html::escape()` for output escaping.
- Game page templates (`game.php`, `game_header.php`, `game_history.php`, `games.php`,
  `trophy.php`, `trophies.php`, `home.php`) and `TrophyPage` use `Html::escape()`.
- Admin templates load `Html.php` via `admin/bootstrap.php`; admin request handlers
  (`GameDetailPage`, `CheaterRequestHandler`, etc.) use `Html::escape()`.

### Composer

- The website + tests run without Composer; `wwwroot/composer.json` deps
  (`tustin/psn-php`) are only used by the admin/cron PSN-scanning scripts. `vendor/`
  is gitignored.
