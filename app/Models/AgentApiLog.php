<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line per Agent API request. Stores what was asked and whether data
 * changed, never request or response bodies.
 */
class AgentApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'gym_id',
        'gym_api_client_id',
        'method',
        'path',
        'ability',
        'member_id',
        'status',
        'changed_data',
        'summary',
        'changes',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'changed_data' => 'boolean',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(GymApiClient::class, 'gym_api_client_id');
    }
}
