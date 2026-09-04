# Database schema

All local MySQL tables (see `database/migrations/`). This database is a
**cache and safety net** over ConnectWise (docs/ARCHITECTURE.md), not a
second source of truth — every table that mirrors ConnectWise data carries
the ConnectWise ID it came from (`cw_*` columns).

## `customers`

Local mirror of a ConnectWise company + primary contact, searched by
`App\Models\Customer::search()` for the register's customer lookup.

| Column | Notes |
|---|---|
| `cw_company_id`, `cw_contact_id`, `cw_site_id` | Nullable until ConnectWise create/match succeeds |
| `company_name`, `first_name`, `last_name` | |
| `address_line1/2`, `city`, `state`, `zip` | |
| `phone`, `email` | |
| `cw_approved` | true once the retail auto-approve status/type was applied in ConnectWise |
| `cw_synced_at` | last successful sync with ConnectWise |

## `catalog_items`

Local cache of the ConnectWise product catalog, filtered to the Retail
warehouse at sync time (`App\Services\ConnectWise\CatalogService`).

| Column | Notes |
|---|---|
| `cw_catalog_id` | unique, ConnectWise catalog item ID |
| `identifier` | SKU |
| `description`, `customer_description` | |
| `category`, `subcategory`, `item_type` | |
| `price`, `cost`, `taxable` | |
| `quantity_on_hand` | as of last sync — informational, not a live stock check |
| `is_active` | false = no longer in the Retail warehouse / deactivated |

## `solutions_lists`

One row per checkout ticket. Becomes (or queues to become) a ConnectWise
Miscellaneous invoice.

| Column | Notes |
|---|---|
| `ticket_number` | human-facing, e.g. `RC-000123` — local only, not the ConnectWise invoice number |
| `customer_id` | FK → `customers` |
| `created_by_user_id` | FK → `users`, the associate |
| `status` | `draft` → `submitted` → `invoiced` (or `failed` / `voided`) |
| `subtotal`, `tax_total`, `total` | |
| `payment_method`, `payment_reference` | informational only — this app does not process payment |
| `cw_invoice_id`, `cw_invoice_number` | set once ConnectWise invoice creation succeeds |
| `invoiced_at` | |

## `solutions_list_items`

Line items on a Solutions List. `catalog_item_id` is nullable — a line can
be a manual/non-catalog charge as well as a catalog hit.

## `sync_queue_items`

The retry ledger for the hybrid checkout workflow (docs/ARCHITECTURE.md).
One row per Solutions List that needs (or needed) a retried ConnectWise
push, with `status` (`pending` / `processing` / `succeeded` / `failed`),
`attempts`, `next_attempt_at`, and `last_error` for visibility into what's
outstanding.

## `audit_logs`

Lightweight append-only log for anything that touches ConnectWise or money
— customer creation, invoice creation, retry failures — via
`App\Models\AuditLog::record()`. `context` is a JSON blob of whatever's
relevant to that action.

## `users`

Associates, created on first Microsoft 365 sign-in
(`App\Http\Controllers\Auth\AzureLoginController`). No password is ever
set — `azure_oid` (the immutable Entra object ID) is the real identity key,
`email`/`azure_upn` follow along for display and audit purposes.

## `jobs`, `cache`, `sessions`

Standard Laravel tables (queue backend, cache backend, and session storage
— all backed by MySQL so nothing beyond MySQL is required on shared
hosting; see docs/BLUEHOST_DEPLOYMENT.md).
