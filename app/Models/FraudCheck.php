<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudCheck extends Model
{
    protected $table = 'registration_fraud_checks';

    protected $fillable = [
        'gym_id',
        'member_id',
        'blocklist_entry_id',
        'email',
        'fraud_score',
        'matched_fields',
        'action',
        'ip_address',
        'checked_at',
    ];

    protected $casts = [
        'matched_fields' => 'array',
        'checked_at' => 'datetime',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function blocklistEntry(): BelongsTo
    {
        return $this->belongsTo(MemberBlocklist::class, 'blocklist_entry_id');
    }
}
