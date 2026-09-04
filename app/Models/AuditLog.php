<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'context'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public static function record(string $action, ?int $userId = null, ?string $subjectType = null, ?int $subjectId = null, array $context = []): self
    {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => $context,
        ]);
    }
}
