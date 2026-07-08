# PSN 100% <[https://psn100.net](https://psn100.net)>

## What is PSN 100%?

PSN 100% is a trophy tracking platform dedicated to creating the ultimate 'clean' trophy list. By merging game stacks and filtering out unobtainable trophies, we provide a unified list of unique, earnable trophies. This ensures every user competes on a level playing field, without the need to replay titles or miss out due to technical issues or retired services.

To maintain a competitive edge, PSN 100% calculates statistics exclusively from the top 10,000 players, offering more accurate benchmarks for dedicated hunters. Built by trophy hunters, for trophy hunters.

## What isn't PSN 100%?

PSN 100% is not a community for discussion (forum), gaming/boosting sessions or trophy guides. Other sites already handle this with greatness, please use them.

## Stack

- **PHP:** 8.5
- **MySQL:** 8.4

## Configuration

### Database

The app connects using `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. Values are read
from `$_ENV` when populated, and fall back to `getenv()` otherwise. Missing configuration
throws `DatabaseConnectionException` instead of attempting a connection with empty credentials.

When using PHP's built-in server, pass `-d variables_order=EGPCS` (or export the variables in
your shell) so `$_ENV` is populated. See `AGENTS.md` for a full local dev setup.

### Reverse proxies

Queue submissions and player reports rate-limit by client IP using `REMOTE_ADDR` by default.

Behind a trusted reverse proxy, set `TRUSTED_PROXY_IPS` to a comma-separated list of proxy
addresses. When the direct client is listed, the leftmost valid `X-Forwarded-For` address is
used. Only enable this when the proxy strips untrusted `X-Forwarded-For` values from clients.

```bash
export TRUSTED_PROXY_IPS=127.0.0.1,10.0.0.1
```

## Deployment

### Admin access

Admin pages require a row in `admin_user`. Create an account with a bcrypt hash:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
INSERT INTO admin_user (username, password_hash) VALUES ('admin', '$2y$10$...');
```

### Apache hardening

Production should layer Apache rules on top of the application:

- **`wwwroot/admin/.htaccess.example`** — HTTP Basic authentication in front of the admin UI.
  Copy to `admin/.htaccess` and set `AuthUserFile` to your server password file.
- **`wwwroot/cron/.htaccess.example`** — deny all web clients except the host that runs
  scheduled jobs. Copy to `cron/.htaccess` and replace `127.0.0.1` with that server's IP.

Cron scripts also refuse non-CLI execution in PHP (`CronCliAccessGuard`), which protects
environments where `.htaccess` is not applied (for example the PHP built-in server).

### Schema updates

Import `database/psn100.sql` on fresh databases. Existing deployments need these tables
for abuse controls:

- `ip_rate_limit` — fixed-window IP rate limits for public JSON endpoints
- `admin_login_throttle` — failed admin login tracking and temporary lockouts

Queue polling (`check_queue_position.php`) requires a `poll_token` from the CSRF-protected
`add_to_queue.php` response (60 requests per IP per minute). `scan_log_poll.php` allows 30
per IP per minute. Admin login locks an IP for 15 minutes after five failed attempts.

## Security

`init.php` sends standard security headers including an enforced `Content-Security-Policy`
(see `ContentSecurityPolicy`). Scripts and styles are served from `'self'`; Bootstrap and
Popper are vendored under `wwwroot/lib/`. User-facing output escaping uses `Html::escape()`.
Admin logout requires POST with CSRF validation and calls `session_destroy()`.

## Tests

Run the suite with `php tests/run.php` (custom runner, no PHPUnit). Most tests use in-memory
SQLite and do not need MySQL.

Optional integration tests (including IP lock acquisition) run when `PSN100_INTEGRATION_TEST_DB=1`
and a reachable `DB_*` configuration are available.

## Merge guideline priorities

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
