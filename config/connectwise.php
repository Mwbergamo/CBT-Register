<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ConnectWise Manage connection (on-premise)
    |--------------------------------------------------------------------------
    |
    | "server" is the hostname of your on-premise ConnectWise Manage install,
    | e.g. cw.codebluetechnology.com. The API "codebase" segment (things like
    | v2025_3/) is version-specific and is normally discovered automatically
    | at runtime via the /login/companyinfo/{company_id} endpoint and then
    | cached — see App\Services\ConnectWise\ConnectWiseClient::baseUri().
    | You can also pin it here (CONNECTWISE_CODEBASE) to skip that lookup.
    |
    */

    'server' => env('CONNECTWISE_SERVER'),
    'codebase' => env('CONNECTWISE_CODEBASE'), // e.g. "v2025_3/" — optional, auto-discovered if blank
    'company_id' => env('CONNECTWISE_COMPANY_ID'),

    // API member credentials — see docs/CONNECTWISE_SETUP.md for how to create these.
    'public_key' => env('CONNECTWISE_PUBLIC_KEY'),
    'private_key' => env('CONNECTWISE_PRIVATE_KEY'),

    // Integrator "clientId" header, registered at developer.connectwise.com.
    'client_id' => env('CONNECTWISE_CLIENT_ID'),

    // Request timeout (seconds) — registers are on the sales floor, fail fast
    // and let the sync queue retry rather than hang the UI.
    'timeout' => env('CONNECTWISE_TIMEOUT', 8),

    /*
    |--------------------------------------------------------------------------
    | Retail store defaults
    |--------------------------------------------------------------------------
    */

    // The ConnectWise inventory location ("Warehouse") that holds the <1000
    // retail SKUs. Catalog search/sync is scoped to this warehouse so the
    // register never has to search the full ~17k item catalog.
    'retail_warehouse_id' => env('CONNECTWISE_RETAIL_WAREHOUSE_ID'),
    'retail_warehouse_name' => env('CONNECTWISE_RETAIL_WAREHOUSE_NAME', 'Retail'),

    // Company/contact defaults applied to walk-in retail customers so they
    // are immediately usable for invoicing without a manual approval step.
    // Confirm these IDs in your own Manage instance (Setup Tables) — see
    // docs/CONNECTWISE_SETUP.md. Do NOT reuse your business-office (terms
    // account) status/type here.
    'retail_company_status_id' => env('CONNECTWISE_RETAIL_COMPANY_STATUS_ID'),
    'retail_company_type_id' => env('CONNECTWISE_RETAIL_COMPANY_TYPE_ID'),
    'retail_company_site_name' => env('CONNECTWISE_RETAIL_SITE_NAME', 'Retail Store'),

    // Board / invoicing defaults used when creating the Miscellaneous invoice.
    'invoice_type' => env('CONNECTWISE_INVOICE_TYPE', 'Miscellaneous'),
    'gl_billing_account_id' => env('CONNECTWISE_GL_BILLING_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Catalog sync
    |--------------------------------------------------------------------------
    */

    // How catalog items are recognized as "Retail". CONDITIONS is a raw
    // ConnectWise Manage API "conditions" query fragment — confirm the exact
    // property path against your catalog schema (category/subcategory vs.
    // an inventory/warehouse join) once you can see real catalog data.
    'catalog_filter_conditions' => env(
        'CONNECTWISE_CATALOG_CONDITIONS',
        'inactiveFlag=false'
    ),

    'catalog_sync_page_size' => env('CONNECTWISE_CATALOG_PAGE_SIZE', 200),
];
