# ConnectWise Manage API setup

Everything here is a one-time setup task inside ConnectWise Manage, done by
whoever has Manage admin rights. None of it requires touching this
codebase — it just produces the values that go in your `.env` file.

## 1. Create an API Member and generate keys

ConnectWise Manage authenticates API calls as a dedicated **API Member**
(not a regular technician login), using a public/private key pair.

1. In Manage: **System > Members > API Members** → add a new API Member.
   Give it a clear name, e.g. `retail-register`.
2. Assign it a **Security Role** scoped to only what this app needs:
   - Companies: Add, Edit, Inquire
   - Contacts: Add, Edit, Inquire
   - Configurations: Inquire (if you associate configs to companies)
   - Procurement/Product Catalog: Inquire
   - Finance/Invoicing (Miscellaneous Invoices): Add, Edit, Inquire
   - Explicitly deny anything else (tickets, agreements, financial reports,
     etc.) — this member should not be able to touch the business-office
     side of Manage.
3. On that API Member, go to the **API Keys** tab and generate a key pair.
   You'll get a **Public Key** and a **Private Key** — the private key is
   shown once, save it immediately.
4. These map to `.env`:
   ```
   CONNECTWISE_COMPANY_ID=<your ConnectWise company ID / login prefix>
   CONNECTWISE_PUBLIC_KEY=<public key>
   CONNECTWISE_PRIVATE_KEY=<private key>
   ```

## 2. Register an Integrator "clientId"

Every ConnectWise Manage REST API call also requires a `clientId` header —
a GUID that identifies the *application* calling the API (separate from the
API Member's keys, which identify *who*).

1. Go to <https://developer.connectwise.com/ClientID>, sign in, and
   register a new client ("CodeBlue Retail Register" or similar).
2. It issues a GUID. Set:
   ```
   CONNECTWISE_CLIENT_ID=<the guid>
   ```

## 3. Server / base URL

```
CONNECTWISE_SERVER=<your on-premise Manage hostname, e.g. cw.codebluetechnology.com>
```

Leave `CONNECTWISE_CODEBASE` blank — the app discovers the versioned API
path automatically (via `/login/companyinfo/{companyId}`) and caches it for
a day. If you'd rather pin it (skips that lookup), find your codebase by
visiting `https://{server}/login/companyinfo/{companyId}` yourself and
copying the `Codebase` value, e.g. `CONNECTWISE_CODEBASE=v2025_3/`.

## 4. "Auto-approve" retail customers — pick the status/type

ConnectWise doesn't have one universal "approved" checkbox; the gate you
described (a new company must be approved before it can be invoiced) is
almost certainly modeled as a **Company Status** in your instance (Setup
Tables > Company Statuses), possibly paired with a **Company Type**.

1. In Manage, look at **System > Setup Tables > Company Status**. You're
   looking for whatever status your business-office companies sit in
   *after* someone with rights approves them for invoicing (commonly
   something like "Active").
2. Rather than reusing that exact status for retail walk-ins, we'd
   recommend creating a **separate status** — e.g. "Retail — Active" — so
   retail customers are clearly distinguishable from terms/business-office
   accounts in reporting, even though both are invoice-ready. Note its ID
   (visible in the URL when editing it, or via the API).
3. Optionally do the same with **Company Type** (Setup Tables > Company
   Types) — e.g. a "Retail" type.
4. Set:
   ```
   CONNECTWISE_RETAIL_COMPANY_STATUS_ID=<id>
   CONNECTWISE_RETAIL_COMPANY_TYPE_ID=<id, optional>
   ```

This is applied automatically to every new customer the register creates
(`App\Services\ConnectWise\CompanyService::createRetailCustomer()`) — that
company is invoice-ready the moment it's created, with no one needing to
manually approve it.

## 5. Create the "Retail" inventory location (warehouse)

You mentioned creating this already — for reference, it's under
**Procurement > Warehouse Setup > Warehouses** (or similar, depending on
version). Once created:

1. Move/assign the <1,000 retail SKUs into it.
2. Find its warehouse ID (via the API or the URL when editing it) and set:
   ```
   CONNECTWISE_RETAIL_WAREHOUSE_ID=<id>
   CONNECTWISE_RETAIL_WAREHOUSE_NAME=Retail
   ```
3. **Important — confirm the filter with real data.** How catalog items
   relate to a warehouse varies by Manage version: some track it as a
   Category/Subcategory on the catalog item itself, others as a separate
   inventory-by-warehouse join. Once you can see your real catalog data
   (via the Manage UI or a test API call to `procurement/catalog`), check
   `CONNECTWISE_CATALOG_CONDITIONS` in `.env` and
   `config/connectwise.php` → `catalog_filter_conditions`, and adjust the
   condition string in `App\Services\ConnectWise\CatalogService::sync()` to
   match. This is the one place the exact ConnectWise query needs your
   real catalog schema to finalize.

## 6. Miscellaneous invoice shape

`App\Services\ConnectWise\InvoiceService::createMiscInvoice()` posts to
`finance/invoices` with a `Miscellaneous`-style payload (company, invoice
date, line items) and then `PATCH`es `closedFlag` to `true`. Before go-live,
create one test invoice by hand through the API (e.g. with Postman/curl,
using the same API Member credentials) against a **test/sandbox company**,
compare the exact fields your Manage version expects, and adjust that one
method to match. This is deliberately isolated to a single method so that
adjustment doesn't touch anything else.

## 7. Smoke-test before going live

Once the values above are all in `.env`:

```bash
php artisan tinker
>>> app(App\Services\ConnectWise\ConnectWiseClient::class)->get('company/companies', ['pageSize' => 1]);
```

If that returns a company record instead of an error, the connection and
auth are working. Then run a real catalog sync:

```bash
php artisan connectwise:sync-catalog
```

and check `catalog_items` in the database has the expected retail rows
before pointing the register UI at it.
