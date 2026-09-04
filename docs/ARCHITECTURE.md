# Architecture

## What this is

A Laravel 13 app (PHP/MySQL, Livewire for the interactive UI, no Node/npm
build step) that CodeBlue retail associates use to check customers out. It
talks to an on-premise ConnectWise Manage install for the source-of-truth
customer and product data, and for the invoice it ultimately creates.

## Why PHP/Laravel

Bluehost shared hosting (register.codebluetechnology.com) runs PHP/MySQL
natively via cPanel; Node.js needs a VPS/Cloud/Dedicated plan. Laravel +
Livewire keeps everything — routing, the database layer, and the
interactive register UI — in one PHP codebase with **no Node runtime
required in production**: Tailwind CSS is loaded from its CDN build (no
compile step) and Livewire ships its own JS assets through a Laravel route,
not a bundler. That means the exact same deploy works whether you end up on
Bluehost shared hosting, a VPS, or anywhere else that runs PHP + MySQL.

## The three systems

```
┌─────────────────┐        ┌──────────────────────┐        ┌───────────────────────┐
│   Associate's    │        │   register-checkout    │        │  ConnectWise Manage    │
│ tablet/phone/PC  │ ─────▶ │   (Laravel, MySQL)     │ ─────▶ │     (on-premise)       │
│   (browser)      │ ◀───── │                        │ ◀───── │                        │
└─────────────────┘        └──────────────────────┘        └───────────────────────┘
                                     │
                                     ▼
                            local MySQL cache:
                            customers, catalog_items,
                            solutions_lists (+items),
                            sync_queue_items, audit_logs
```

The local database is a **cache and a safety net**, not a second system of
record. Customers and catalog items are always meant to match ConnectWise;
the local copy exists so search is fast (no round trip to ConnectWise on
every keystroke) and so a register can keep working through a brief
ConnectWise outage.

## The checkout workflow: hybrid real-time + retry queue

Per your call on this: checkout tries to create + close the ConnectWise
Miscellaneous invoice **synchronously**, in the same request, so the
associate normally sees a real invoice number immediately
(`App\Services\ConnectWise\InvoiceService::pushToConnectWise()`, called from
`App\Livewire\Checkout::checkout()`).

If that call fails — ConnectWise unreachable, timing out, mid-maintenance —
the sale still completes locally:

1. The `solutions_lists` row is saved with `status = 'submitted'` (not
   `'invoiced'`) and the printable receipt is still available immediately
   (`App\Http\Controllers\SolutionsListPrintController`).
2. A `sync_queue_items` row is created recording the failure.
3. `App\Jobs\PushSolutionsListToConnectWise` is queued with backoff (1m,
   5m, 15m, 30m, then hourly, 8 attempts) to retry automatically.
4. If every retry is exhausted, the list is marked `status = 'failed'` and
   an `audit_logs` row is written — see docs/BLUEHOST_DEPLOYMENT.md for how
   to notice this (there's no email/Slack alert wired up yet; the queue
   table itself is the place to check, or wire `App\Jobs\...::failed()` to
   a notification of your choice).

This means an associate never has to wait on ConnectWise being healthy to
finish a sale, while still defaulting to a real-time invoice the vast
majority of the time.

## Customer search & auto-approval

`App\Models\Customer::search()` is a single scope that matches against
name, company, phone, email, address — the same fields the "new customer"
form collects, so search and entry feel like one continuous form (see
`resources/views/livewire/checkout.blade.php`, step 1).

A brand-new customer is created directly in ConnectWise
(`App\Services\ConnectWise\CompanyService::createRetailCustomer()`) with a
configured Company Status/Type applied so it's usable for invoicing
immediately — no manual approval step, because a retail customer is
standing at the register with payment in hand, unlike a terms/business
office account. Which status/type ID means "approved" is **config, not
code** (`CONNECTWISE_RETAIL_COMPANY_STATUS_ID` /
`CONNECTWISE_RETAIL_COMPANY_TYPE_ID`) — see docs/CONNECTWISE_SETUP.md for
how to pick these safely (i.e., not the same ones CodeBlue's business
office uses).

## Catalog search scoped to the Retail warehouse

`App\Services\ConnectWise\CatalogService::sync()` pulls the catalog from
ConnectWise filtered to the "Retail" inventory location and caches it into
`catalog_items` (`App\Console\Commands\SyncConnectWiseCatalog`, scheduled
every 15 minutes — see `routes/console.php`). The register searches that
local cache (`App\Models\CatalogItem::search()`), never the live ~17,000
item catalog, which is what keeps product search fast on a shared-hosting
MySQL database with a plain `LIKE` query instead of needing a search
engine.

## Authentication

No local passwords. Every associate signs in with their CodeBlue Microsoft
365 account (`App\Http\Controllers\Auth\AzureLoginController`, via Laravel
Socialite's Azure provider). Who is *allowed* to sign in is controlled in
Azure AD (app assignment / conditional access), not by anything in this
app's database — see docs/AZURE_AD_SETUP.md.

## What's built vs. what needs your ConnectWise instance to finish

This codebase is a complete, working scaffold: every screen, model,
migration, service class and background job described above is written and
covered by feature tests (`tests/`). What it can't include, because it
needs your actual ConnectWise Manage instance to determine, is called out
explicitly in code comments and in docs/CONNECTWISE_SETUP.md:

- The exact field(s) that represent "approved" for a new company in your
  Manage setup tables.
- The exact query/property used to scope the catalog to one warehouse
  (category vs. a warehouse join — depends on how your catalog is
  organized).
- The exact Miscellaneous-invoice line item shape for your Manage version.

Each of these is isolated to one method in `app/Services/ConnectWise/`, so
confirming them against your instance's own REST API reference is a small,
contained change — not a rewrite.
