# Deploying to Bluehost (register.codebluetechnology.com)

This app needs **PHP 8.3+** and **MySQL**, and has **no Node/npm build
step** — Tailwind and Livewire's JS both load without a bundler (see
docs/ARCHITECTURE.md), so a standard Bluehost shared hosting / cPanel plan
is enough.

## 1. PHP version

In cPanel: **MultiPHP Manager** → set the domain to PHP 8.3 or newer.
Then **MultiPHP INI Editor** for that domain, confirm/raise:
- `memory_limit` ≥ 256M
- `max_execution_time` ≥ 60
- `upload_max_filesize` / `post_max_size` — defaults are fine, this app
  doesn't take file uploads.

## 2. Domain document root

Point `register.codebluetechnology.com` at this project's `public/`
directory specifically (not the project root) — that's a hard Laravel
requirement, since `public/` is the only directory meant to be
web-accessible; everything else (`.env`, `app/`, etc.) must stay outside
the web root.

In cPanel's **Domains** tool, when you create the subdomain, set its
**Document Root** to something like
`/home/<cpanel-user>/register-checkout/public`, with the rest of the app
(`app/`, `config/`, `.env`, etc.) living in `/home/<cpanel-user>/register-checkout/`
one level up — not inside `public_html`.

## 3. Get the code onto the server

Easiest via SSH (cPanel → Terminal, or an SSH client) since Bluehost's SSH
access *does* have normal outbound internet, unlike this build sandbox:

```bash
cd ~
git clone <your private git remote> register-checkout   # or upload the zip and unzip it
cd register-checkout
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

If you don't have git set up, upload the project as a zip via cPanel's
File Manager and extract it, then still run `composer install` over SSH —
the `vendor/` directory should not be committed to git or shipped in the
zip (see `.gitignore`), it needs to be built on the server (or built
locally and uploaded) using its actual PHP version.

## 4. MySQL database

**cPanel → MySQL Databases**: create a database and a user with all
privileges on it, then fill in `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<cpanel-prefixed db name>
DB_USERNAME=<cpanel-prefixed db user>
DB_PASSWORD=<password>
```

Then:

```bash
php artisan migrate --force
```

## 5. Fill in the rest of `.env`

Everything from docs/CONNECTWISE_SETUP.md and docs/AZURE_AD_SETUP.md, plus:

```
APP_NAME="CodeBlue Register"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://register.codebluetechnology.com

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

(`database` for session/cache/queue is deliberate — it needs nothing beyond
MySQL, which every Bluehost plan already gives you; no Redis to install.)

## 6. SSL

Bluehost issues a free Let's Encrypt certificate per domain/subdomain —
**cPanel → SSL/TLS Status**, run AutoSSL for the subdomain if it isn't
already covering it. HTTPS is required here, not optional — Azure AD will
refuse to redirect back to a plain-http callback URL.

## 7. Cron: the piece shared hosting needs instead of a queue worker

A normal Laravel deployment runs `php artisan queue:work` as a
long-running background process. Shared hosting doesn't allow long-running
processes, so instead this app is set up (see `routes/console.php`) to
drain the queue in short bursts on a schedule.

**cPanel → Cron Jobs**, add exactly one entry, every minute:

```
* * * * * cd /home/<cpanel-user>/register-checkout && php artisan schedule:run >> /dev/null 2>&1
```

That single cron line drives two scheduled tasks already defined in
`routes/console.php`:

- `connectwise:sync-catalog` every 15 minutes — keeps the Retail catalog
  cache fresh.
- `queue:work --stop-when-empty --max-time=50` every minute — processes
  anything in the ConnectWise retry queue (failed invoice pushes — see
  docs/ARCHITECTURE.md's hybrid workflow section) and any other queued
  job, then exits (so it never overlaps the next minute's cron tick).

If cron isn't running (check cPanel → Cron Jobs → email delivery, or the
`jobs`/`sync_queue_items` tables never draining), the app still works —
checkout just falls further and further behind on retries until cron is
fixed.

## 8. Storage permissions

Laravel needs `storage/` and `bootstrap/cache/` writable by the web server
user (usually already true after upload, but if you hit a "permission
denied" error):

```bash
chmod -R 775 storage bootstrap/cache
```

## 9. First-run checklist

- [ ] `php artisan migrate --force` ran clean
- [ ] `php artisan connectwise:sync-catalog` pulls real Retail items
- [ ] Sign-in with Microsoft works end-to-end (redirect → consent → back to
      `/register`)
- [ ] A test checkout against a **ConnectWise sandbox/test company**
      creates and closes an invoice you can see in Manage
- [ ] Cron is confirmed running (add a temporary `Log::info()` in a
      scheduled command and check `storage/logs/laravel.log` after a
      minute, then remove it)
- [ ] `APP_DEBUG=false` in production `.env` (don't leave stack traces
      visible to associates or customers)

## Updating the app after go-live

```bash
cd ~/register-checkout
git pull   # or re-upload changed files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
