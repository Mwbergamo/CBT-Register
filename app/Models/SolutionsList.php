<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'ticket_number', 'customer_id', 'created_by_user_id', 'status',
    'subtotal', 'tax_total', 'total', 'payment_method', 'payment_reference',
    'cw_invoice_id', 'cw_invoice_number', 'invoiced_at', 'notes',
])]
class SolutionsList extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'invoiced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SolutionsList $list) {
            $list->ticket_number ??= static::nextTicketNumber();
        });
    }

    public static function nextTicketNumber(): string
    {
        // Human-friendly, monotonic-enough for a single-store register.
        // Not used as the ConnectWise invoice number — that comes from
        // ConnectWise once the invoice is created.
        $next = (static::max('id') ?? 0) + 1;

        return 'RC-'.Str::padLeft((string) $next, 6, '0');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SolutionsListItem::class)->orderBy('sort_order');
    }

    public function syncQueueItems(): HasMany
    {
        return $this->hasMany(SyncQueueItem::class);
    }

    public function recalculateTotals(float $taxRate = 0.0): void
    {
        $this->loadMissing('items');

        $subtotal = $this->items->sum(fn (SolutionsListItem $item) => (float) $item->line_total);
        $taxable = $this->items->where('taxable', true)->sum(fn (SolutionsListItem $item) => (float) $item->line_total);
        $tax = round($taxable * $taxRate, 2);

        $this->subtotal = $subtotal;
        $this->tax_total = $tax;
        $this->total = round($subtotal + $tax, 2);
    }
}
