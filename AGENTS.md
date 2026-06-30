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
  (param key is `q`); valid names match `^[\w\-]{3,16}$` and are inserted into
  `player_queue`. This is a quick way to exercise a real DB write.

### Tests

- Run the full suite with `php tests/run.php` (custom runner, no PHPUnit). It does not
  need MySQL — DB-backed tests use in-memory SQLite, so `php8.5-sqlite3` must be
  installed (it is, in the image).

### Composer

- The website + tests run without Composer; `wwwroot/composer.json` deps
  (`tustin/psn-php`) are only used by the admin/cron PSN-scanning scripts. `vendor/`
  is gitignored.
