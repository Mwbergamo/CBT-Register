<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'solutions_list_id', 'status', 'attempts', 'next_attempt_at',
    'last_attempted_at', 'last_error',
])]
class SyncQueueItem extends Model
{
    protected function casts(): array
    {
        return [
            'next_attempt_at' => 'datetime',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function solutionsList(): BelongsTo
    {
        return $this->belongsTo(SolutionsList::class);
    }
}
