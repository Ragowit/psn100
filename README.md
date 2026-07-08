# PSN 100% <[https://psn100.net](https://psn100.net)>

## What is PSN 100%?
PSN 100% is a trophy tracking platform dedicated to creating the ultimate 'clean' trophy list. By merging game stacks and filtering out unobtainable trophies, we provide a unified list of unique, earnable trophies. This ensures every user competes on a level playing field, without the need to replay titles or miss out due to technical issues or retired services.

To maintain a competitive edge, PSN 100% calculates statistics exclusively from the top 10,000 players, offering more accurate benchmarks for dedicated hunters. Built by trophy hunters, for trophy hunters.

## What isn't PSN 100%?
PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.

## Technology stack
- **PHP:** 8.5
- **MySQL:** 8.4

## Deployment notes

### Database environment variables

The application reads MySQL credentials from `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
Values are read from `$_ENV` when populated, and fall back to `getenv()` when they are not.

When using PHP's built-in development server, either export the variables in your shell or pass
`-d variables_order=EGPCS` so `$_ENV` is populated:

```bash
cd wwwroot && DB_HOST=127.0.0.1 DB_NAME=psn100 DB_USER=psn100 DB_PASSWORD=psn100 \
php -d variables_order=EGPCS -S 0.0.0.0:8000 /tmp/psn100-router.php
```

If configuration is missing, the app throws a clear `DatabaseConnectionException` instead of
attempting a connection with empty credentials.

### Reverse proxies and client IP limits

Queue submissions and player reports rate-limit by client IP. By default the app uses
`REMOTE_ADDR` only.

If the app runs behind a trusted reverse proxy, set `TRUSTED_PROXY_IPS` to a comma-separated
list of proxy addresses that may append `X-Forwarded-For`. When the direct client is a listed
proxy, the leftmost valid IP from `X-Forwarded-For` is used for rate limiting.

Example:

```bash
export TRUSTED_PROXY_IPS=127.0.0.1,10.0.0.1
```

Only enable this when the proxy strips untrusted `X-Forwarded-For` values from clients.

### Admin access

Admin pages require a row in the `admin_user` table. Create an account with a bcrypt hash:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
INSERT INTO admin_user (username, password_hash) VALUES ('admin', '$2y$10$...');
```

### Protecting `/admin` and `/cron`

Production should layer Apache rules on top of the application:

- **`wwwroot/admin/.htaccess.example`** — HTTP Basic authentication in front of the admin UI.
  Copy to `admin/.htaccess` and set `AuthUserFile` to your server password file.
- **`wwwroot/cron/.htaccess.example`** — deny all web clients except the host that runs
  scheduled jobs. Copy to `cron/.htaccess` and replace `127.0.0.1` with that server's IP.

Cron entry scripts also refuse non-CLI execution in PHP (`CronCliAccessGuard`), which
protects environments where `.htaccess` is not applied (for example the PHP built-in
development server). Admin remains reachable in dev so you can exercise the login flow;
use the Basic-auth template only on production Apache hosts.

### Abuse resistance (queue polling, scan log, admin login)

Existing deployments need the new tables from `database/psn100.sql`:

- `ip_rate_limit` — fixed-window IP rate limits for public JSON endpoints
- `admin_login_throttle` — failed admin login tracking and temporary lockouts

Queue status polling (`check_queue_position.php`) requires a `poll_token` issued by the
CSRF-protected `add_to_queue.php` response and stored in the visitor session. Poll
requests are limited to 60 per IP per minute; `scan_log_poll.php` is limited to 30 per
IP per minute. Admin login locks an IP for 15 minutes after five failed attempts.

### Security hardening (Phase 3)

- Queue and report submissions return a friendly busy message (HTTP 503) when the MySQL
  per-IP advisory lock cannot be acquired, instead of an uncaught 500.
- Homepage queue polling renders structured `messageParts` with DOM APIs instead of
  `innerHTML` for server responses.
- Admin logout requires POST + CSRF and calls `session_destroy()`.
- Worker refresh tokens can be edited from the admin Workers page (alongside NPSSO).
- Player and game URLs use `rawurlencode()` consistently via `PlayerUrlBuilder`.
- `Html::escape()` is the shared HTML-escaping helper for new code.
- `Content-Security-Policy-Report-Only` is sent from `init.php` to collect violations
  before enforcing a stricter policy.

### Security hardening (Phase 4)

- Unified date localization via `localized-date-formatter.js` removes per-row inline
  scripts from game, trophy, player, and about pages.
- Changelog regrouping moved to `changelog-date-grouping.js` with `cloneNode` instead
  of `innerHTML` round-trips.
- Class-layer HTML escaping migrated to `Html::escape()` in changelog, game header,
  history, and message sanitizer code paths.

### Security hardening (Phase 5)

- Removed unused jQuery from the public footer and dropped `code.jquery.com` from the
  CSP Report-Only allowlist.

Optional MySQL integration tests (including IP lock acquisition) run when
`PSN100_INTEGRATION_TEST_DB=1` and a reachable `DB_*` configuration are available.

## Merge Guideline Priorities
1. Available > Delisted
2. English language > Other language
3. Digital > Physical
4. Remaster/Remake > Original
5. PS5 > PS4 > PS3 > PSVITA
6. Collection/Bundle > Single entry

## Thanks
- PSNP+ <[https://psnp-plus.netlify.app/](https://psnp-plus.netlify.app/)> ([HusKy](https://forum.psnprofiles.com/profile/229685-husky/)) for allowing PSN100 to use the "Unobtainable Trophies Master List" data.

## Other sites
- PSN Profiles <[https://psnprofiles.com/](https://psnprofiles.com/)>
- PlaystationTrophies <[https://www.playstationtrophies.org/](https://www.playstationtrophies.org/)>
- PSN Trophy Leaders <[https://psntrophyleaders.com/](https://psntrophyleaders.com/)>
- TrueTropies <[https://www.truetrophies.com/](https://www.truetrophies.com/)>
- Exophase <[https://www.exophase.com/](https://www.exophase.com/)>
