<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cw_catalog_id', 'identifier', 'description', 'customer_description',
    'category', 'subcategory', 'item_type', 'price', 'cost', 'taxable',
    'quantity_on_hand', 'is_active', 'cw_synced_at',
])]
class CatalogItem extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'taxable' => 'boolean',
            'is_active' => 'boolean',
            'cw_synced_at' => 'datetime',
        ];
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        $query->where('is_active', true);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('identifier', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('customer_description', 'like', $like);
        });
    }
}
