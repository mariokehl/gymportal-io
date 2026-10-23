<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedFilter extends Model
{
    public const CONTEXT_MEMBERS = 'members';

    protected $fillable = [
        'gym_id',
        'user_id',
        'context',
        'name',
        'definition',
    ];

    protected $casts = [
        'definition' => 'array',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toListArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'definition' => $this->definition,
        ];
    }
}
