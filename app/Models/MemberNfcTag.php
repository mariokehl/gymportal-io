<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional NFC tag of a member. The primary tag lives in
 * MemberAccessConfig::$nfc_uid and is checked first on every scan.
 */
class MemberNfcTag extends Model
{
    protected $fillable = [
        'uid',
        'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    /**
     * Get the access configuration the tag belongs to
     */
    public function accessConfig(): BelongsTo
    {
        return $this->belongsTo(MemberAccessConfig::class, 'member_access_config_id');
    }
}
