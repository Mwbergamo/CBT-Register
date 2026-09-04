<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $list->ticket_number }} — CodeBlue Technology</title>
    <style>
        body { font-family: -apple-system, Segoe UI, Arial, sans-serif; color: #0f172a; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; margin-bottom: 0; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { text-align: left; padding: 0.5rem 0.25rem; border-bottom: 1px solid #e2e8f0; }
        th { font-size: 0.75rem; text-transform: uppercase; color: #64748b; }
        .totals td { border: none; padding: 0.15rem 0.25rem; }
        .totals .label { color: #64748b; }
        .grand { font-size: 1.1rem; font-weight: 700; }
        .status { display: inline-block; padding: 0.15rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .status.invoiced { background: #d1fae5; color: #065f46; }
        .status.pending { background: #fef3c7; color: #92400e; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right; margin-bottom:1rem;">
        <button onclick="window.print()">Print</button>
    </div>

    <h1>CodeBlue Technology — Retail</h1>
    <p class="muted">{{ $list->ticket_number }} &middot; {{ $list->created_at->format('M j, Y g:i A') }}</p>

    <p>
        <span class="status {{ $list->status === 'invoiced' ? 'invoiced' : 'pending' }}">
            {{ $list->status === 'invoiced' ? 'Invoice #'.$list->cw_invoice_number.' — Closed' : 'Invoice pending sync' }}
        </span>
    </p>

    <p>
        <strong>{{ $list->customer->fullName() ?: $list->customer->company_name }}</strong><br>
        @if ($list->customer->address_line1)
            {{ $list->customer->address_line1 }}<br>
            {{ collect([$list->customer->city, $list->customer->state, $list->customer->zip])->filter()->join(', ') }}<br>
        @endif
        @if ($list->customer->phone) {{ $list->customer->phone }}<br> @endif
        @if ($list->customer->email) {{ $list->customer->email }} @endif
    </p>

    <table>
        <thead>
            <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            @foreach ($list->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="max-width: 260px; margin-left: auto;">
        <tr><td class="label">Subtotal</td><td style="text-align:right">${{ number_format($list->subtotal, 2) }}</td></tr>
        <tr><td class="label">Tax</td><td style="text-align:right">${{ number_format($list->tax_total, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td style="text-align:right">${{ number_format($list->total, 2) }}</td></tr>
    </table>

    <p class="muted" style="margin-top:2rem; font-size:0.8rem;">
        Served by {{ $list->createdBy->name ?? 'CodeBlue Technology' }} &middot; Payment: {{ ucfirst($list->payment_method ?? '—') }}
    </p>
</body>
</html>
