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
