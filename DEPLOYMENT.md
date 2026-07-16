# Deployment checklist

Use this checklist when deploying PSN 100% to a production Apache host. The application
also enforces access in PHP (`CronCliAccessGuard`, admin session auth, rate limits), but
Apache rules should be enabled on every production server.

## Quick checklist

- [ ] `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` are set for the web and cron environments
- [ ] `database/psn100.sql` is imported (fresh install) or required tables are migrated (existing install)
- [ ] `wwwroot/vendor/` is installed (`composer install` in `wwwroot/`) for admin/cron PSN scanning
- [ ] **`wwwroot/admin/.htaccess`** is created from `wwwroot/admin/.htaccess.example`
- [ ] **`wwwroot/cron/.htaccess`** is created from `wwwroot/cron/.htaccess.example`
- [ ] Admin HTTP Basic password file exists and `AuthUserFile` points at it
- [ ] At least one `admin_user` row exists for application login
- [ ] Cron jobs are scheduled via **CLI**, not HTTP
- [ ] Post-deploy verification (see [Verification](#verification)) passes

## Prerequisites

- **Apache 2.4+** with `AllowOverride` enabled for `wwwroot/` so `.htaccess` files are read
- **`mod_auth_basic`** enabled (admin HTTP Basic authentication)
- **PHP 8.5** with extensions used by the app (`pdo_mysql`, `curl`, `gd`, `mbstring`, `intl`, `bcmath`, `gmp`, `zip`)
- **MySQL 8.4+** (production target; the structure-only schema may import on 8.0 for local smoke tests, but `database/mysql84_histograms.sql` requires 8.4 `AUTO UPDATE` histograms)
- Paths to password files and cron runner IP addresses decided before go-live

Committed templates (safe to version):

| Template | Production file (gitignored) |
|----------|------------------------------|
| `wwwroot/admin/.htaccess.example` | `wwwroot/admin/.htaccess` |
| `wwwroot/cron/.htaccess.example` | `wwwroot/cron/.htaccess` |

`wwwroot/admin/.htpasswd` is also gitignored if you store a local copy beside the site.

## Admin HTTP Basic authentication

The admin UI uses **two layers**: Apache HTTP Basic auth (network gate) and application login
against `admin_user` (session + CSRF). Enable both in production.

### 1. Create the password file

From the server (adjust username and path):

```bash
htpasswd -c /path/to/.htpasswds/admin/passwd admin-gate
```

Use `-c` only when creating a new file. Omit it to add more users later.

### 2. Install `admin/.htaccess`

```bash
cp wwwroot/admin/.htaccess.example wwwroot/admin/.htaccess
```

Edit `wwwroot/admin/.htaccess` and set `AuthUserFile` to the absolute path of the file from
step 1:

```apache
AuthUserFile "/path/to/.htpasswds/admin/passwd"
```

Leave the other directives as in the example (`AuthName`, `AuthType Basic`, `Require valid-user`).

### 3. Create an application admin account

Generate a bcrypt hash:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Insert into the database:

```sql
INSERT INTO admin_user (username, password_hash) VALUES ('admin', '$2y$10$...');
```

Application login is rate-limited (`admin_login_throttle`: five failed attempts per IP, then
a 15-minute lockout). HTTP Basic credentials are separate from this username/password.

## Cron directory IP allowlisting

Cron scripts must run from the **CLI**. The `.htaccess` in `cron/` is defense in depth: it
rejects web requests before PHP runs. `CronCliAccessGuard` in PHP also returns **403** for any
HTTP request under `/cron/`, including from the allowlisted IP.

### 1. Install `cron/.htaccess`

```bash
cp wwwroot/cron/.htaccess.example wwwroot/cron/.htaccess
```

### 2. Set the cron runner IP

Replace `127.0.0.1` with the address of the host that runs scheduled jobs:

- **Same machine as the web server:** keep `127.0.0.1`
- **Dedicated cron host:** use that host's outbound IP as seen by the web server
- **Multiple runners:** add additional `Require ip` lines (Apache 2.4)

Example for Apache 2.4+:

```apache
Require all denied
Require ip 127.0.0.1
Require ip 10.0.0.5
```

An Apache 2.2 equivalent is commented in the template.

### 3. Schedule CLI cron jobs

Run from the repository root (or use absolute paths). Each entry script loads
`cron/bootstrap.php`, which calls `CronCliAccessGuard::requireCliExecution()`.

| Script | Typical schedule |
|--------|------------------|
| `php wwwroot/cron/5th_minute.php` | Every 5 minutes |
| `php wwwroot/cron/30th_minute.php` | Every 30 minutes |
| `php wwwroot/cron/hourly.php` | Hourly |
| `php wwwroot/cron/daily.php` | Daily |
| `php wwwroot/cron/weekly.php` | Weekly |

Pass the same `DB_*` environment variables (or php.ini / pool config) that the web app uses.
Cron jobs use intentional infinite retry loops; they should be supervised (systemd, cron
wrapper, or monitoring) so a crashed process is restarted.

**Do not** invoke these URLs over HTTP, even from the allowlisted IP—the PHP guard will
respond with `403 Forbidden`.

## Verification

Run after copying and editing both `.htaccess` files.

### Admin

```bash
# Expect 401 Unauthorized (HTTP Basic challenge)
curl -s -o /dev/null -w "%{http_code}" https://your-host/admin/login.php

# Expect 200 only after valid HTTP Basic credentials
curl -s -o /dev/null -w "%{http_code}" -u 'admin-gate:secret' https://your-host/admin/login.php
```

Then log in through the browser with your `admin_user` credentials and confirm CSRF-protected
actions work.

### Cron

```bash
# Expect 403 from Apache and/or PHP (never 200)
curl -s -o /dev/null -w "%{http_code}" https://your-host/cron/hourly.php

# CLI must start without "Forbidden" (job may run a long time; Ctrl+C after it begins)
DB_HOST=... DB_NAME=... DB_USER=... DB_PASSWORD=... php wwwroot/cron/hourly.php
```

### Public site

Confirm pretty URLs still route through `wwwroot/.htaccess` (`FallbackResource /index.php`).

## Reverse proxies and rate limits

If the site sits behind a trusted reverse proxy, set `TRUSTED_PROXY_IPS` so queue, report,
and admin throttle logic see the real client IP. See [README.md](README.md#reverse-proxies).

## Development environments

The PHP built-in server does **not** read `.htaccess`:

- `/admin` remains reachable without HTTP Basic (application login still applies)
- `/cron` HTTP requests are blocked by `CronCliAccessGuard` in `init.php`

Local setup details are in [AGENTS.md](AGENTS.md).

## Schema notes for existing databases

Fresh installs: import `database/psn100.sql`.

Upgrades from older deployments may need these tables for abuse controls:

- `ip_rate_limit` — public JSON endpoint rate limits
- `admin_login_throttle` — admin login lockouts

See [README.md](README.md#schema-updates) for limits and behavior.

### MySQL 8.4 optimizer statistics and covering indexes

After schema import or large data changes on MySQL 8.4+, run:

```bash
mysql "$DB_NAME" < database/mysql84_histograms.sql
mysql "$DB_NAME" < database/mysql84_covering_indexes.sql
mysql "$DB_NAME" < database/mysql84_trophy_earned_triggers.sql
```

Histograms use `AUTO UPDATE` on heavily filtered columns (`player.status`,
`trophy_title_player.progress`, `trophy_title_meta.status`/`difficulty`/`rarity_points`,
and others). Histograms help the 8.4 optimizer choose better plans for leaderboard, queue,
and cron queries. Re-run after bulk imports; a weekly `ANALYZE TABLE` cron is optional but
reasonable on busy sites.

`mysql84_covering_indexes.sql` adds descending covering indexes for game leaderboard sorts
and popular/game-list ordering, replaces `trophy_group_player.idx_account_id` with
`(account_id, np_communication_id)`, status CHECK constraints, and migrates
`setting.scan_progress` to JSON. Safe to re-run on upgraded databases.

`player_ranking` is excluded from histograms because `PlayerRankingUpdater` rebuilds and
swaps that table every five minutes, which would invalidate histograms immediately.

`trophy_earned` is excluded: at production scale (billions of rows, hundreds of GiB) an
`ANALYZE TABLE` would run for hours and `AUTO UPDATE` would rebuild histograms on every
InnoDB stats refresh. Existing queries filter by `account_id` (partition key) or
`np_communication_id` (leading primary-key column), so histograms would add little value.

`mysql84_trophy_earned_triggers.sql` recreates the three `trophy_earned` triggers so bulk
deletes can set `@psn100_skip_trophy_count = 1` to skip per-row counter updates (fresh
installs from `psn100.sql` already include this). Safe to re-run; it only replaces trigger
definitions. (`earned` only transitions `0` → `1`, never `1` → `0`.)

Older databases may still carry redundant indexes dropped from the current schema. Apply
(safe to re-run; skips indexes that are already absent):

```bash
mysql "$DB_NAME" < database/mysql84_drop_redundant_indexes.sql
```

Dropped indexes are either redundant with an existing primary/unique/composite key, or
unused by application query predicates (country ranking indexes and
`idx_trophy_count_npwr`). The script also adds `chk_trophy_type` when missing.
