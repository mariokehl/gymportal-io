<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymGoogleSheetIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'google_sheet_enabled',
        'credentials',
        'service_account_email',
        'spreadsheet_id',
        'sheet_url',
        'last_synced_at',
    ];

    protected $casts = [
        'google_sheet_enabled' => 'boolean',
        // Laravel transparently encrypts/decrypts the service account key at rest
        'credentials' => 'encrypted',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Never expose the encrypted credentials when serialising the model.
     */
    protected $hidden = [
        'credentials',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Whether this integration is ready to receive a sync.
     */
    public function isConfigured(): bool
    {
        return $this->google_sheet_enabled
            && ! empty($this->credentials)
            && ! empty($this->spreadsheet_id);
    }

    /**
     * Decode the decrypted service account key into an array.
     */
    public function credentialsArray(): ?array
    {
        if (empty($this->credentials)) {
            return null;
        }

        $decoded = json_decode($this->credentials, true);

        return is_array($decoded) ? $decoded : null;
    }
}
