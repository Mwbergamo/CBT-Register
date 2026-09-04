<?php

return [
    // Simple flat sales-tax rate applied to taxable line items, e.g. 0.053
    // for 5.3%. Leave at 0 if the register's payment terminal / point of
    // sale handles tax separately and this app should just record pretax
    // totals.
    'tax_rate' => (float) env('REGISTER_TAX_RATE', 0.0),
];
