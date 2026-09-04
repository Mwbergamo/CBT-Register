<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cw_company_id', 'cw_contact_id', 'cw_site_id',
    'company_name', 'first_name', 'last_name',
    'address_line1', 'address_line2', 'city', 'state', 'zip',
    'phone', 'email', 'cw_approved', 'cw_synced_at',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'cw_approved' => 'boolean',
            'cw_synced_at' => 'datetime',
        ];
    }

    public function solutionsLists(): HasMany
    {
        return $this->hasMany(SolutionsList::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Search by the same fields the associate types into when entering a
     * new customer, so recall and entry feel like one continuous form.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term);
        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('address_line1', 'like', $like)
                ->orWhere('zip', 'like', $like);

            if ($digits !== '') {
                $q->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }
}
