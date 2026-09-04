# Local development

## Install dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # local dev uses sqlite by default — see .env
php artisan migrate
```

> **Note on how this project was built:** it was scaffolded and written in
> a sandboxed cloud environment whose network policy blocks Packagist
> (Composer's package registry), so `composer install` has **not** been run
> against this codebase yet, and `vendor/` is intentionally not included.
> Every PHP file was hand-written to current Laravel 13 conventions and
> checked with `php -l` (syntax only), and the test suite in `tests/` is
> real and ready to run — but none of it has executed yet. Run
> `composer install` here (or on Bluehost, both have normal internet
> access) and then `php artisan test` as your first step, and treat that as
> the actual first test run of this code. If anything doesn't line up with
> the installed package versions (Laravel/Livewire/Socialite do evolve),
> it should be a small fix, not a rewrite — the architecture and business
> logic are the parts that matter and those don't depend on exact package
> versions.

## Run it

```bash
php artisan serve
```

Visit `http://localhost:8000`. You'll be redirected to `/login`; Microsoft
sign-in needs real Azure AD credentials in `.env` first (see
docs/AZURE_AD_SETUP.md) — until then you can create a local user directly
for UI development:

```bash
php artisan tinker
>>> App\Models\User::factory()->create(['email' => 'you@codebluetechnology.com']);
```

and use Laravel's `Auth::loginUsingId()` via tinker, or temporarily add a
dev-only login route — don't ship that to production.

## Seed some data to develop against

```bash
php artisan tinker
>>> App\Models\Customer::factory()->syncedToConnectWise()->count(10)->create();
>>> App\Models\CatalogItem::factory()->count(50)->create();
```

This lets you exercise the whole register UI (search, cart, checkout)
without a live ConnectWise connection — checkout will still try to reach
ConnectWise for the real invoice push and fail gracefully into the sync
queue (see docs/ARCHITECTURE.md) unless you've configured real credentials.

## Run the tests

```bash
php artisan test
```

The suite fakes all ConnectWise HTTP calls (`Http::fake()`) and uses
sqlite in-memory, so it runs fully offline — see `phpunit.xml` for the
fixed test ConnectWise config and `tests/Feature/*Test.php`.

## Code layout

- `app/Services/ConnectWise/` — the ConnectWise API integration. Start
  here if something needs to change about how the app talks to Manage.
- `app/Livewire/Checkout.php` + `resources/views/livewire/checkout.blade.php`
  — the whole register screen (find/enter customer → build Solutions List
  → checkout), as one component. See the class docblock for why.
- `app/Jobs/PushSolutionsListToConnectWise.php` — the retry side of the
  hybrid checkout workflow.
- `docs/` — everything else you need: ConnectWise setup, Azure AD setup,
  Bluehost deployment, database schema.
