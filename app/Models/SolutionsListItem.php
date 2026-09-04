<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'catalog_item_id', 'identifier', 'description', 'quantity',
    'unit_price', 'taxable', 'line_total', 'sort_order',
])]
class SolutionsListItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'taxable' => 'boolean',
        ];
    }

    public function solutionsList(): BelongsTo
    {
        return $this->belongsTo(SolutionsList::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
