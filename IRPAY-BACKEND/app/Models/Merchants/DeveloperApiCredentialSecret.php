<?php

namespace App\Models\Merchants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeveloperApiCredentialSecret extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'developer_api_credential_id' => 'integer',
        'scope' => 'string',
        'secret_identifier' => 'string',
        'secret_last_four' => 'string',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(DeveloperApiCredential::class, 'developer_api_credential_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function markRotated(): void
    {
        $now = now();
        $this->forceFill([
            'rotated_at' => $now,
            'revoked_at' => $now,
        ])->save();
    }

    public function revoke(): void
    {
        if ($this->revoked_at) {
            return;
        }

        $this->forceFill([
            'revoked_at' => now(),
        ])->save();
    }

    public function touchLastUsed(): void
    {
        $this->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    public static function generateSecret(?int $length = null): string
    {
        $length = $length ?? config('developer-api.secret_length');
        return Str::random($length);
    }

    public static function buildIdentifier(string $secret): string
    {
        $appKey = config('app.key');
        if (Str::startsWith($appKey, 'base64:')) {
            $decoded = base64_decode(Str::after($appKey, 'base64:'));
            if ($decoded !== false) {
                $appKey = $decoded;
            }
        }

        return hash_hmac('sha256', $secret, $appKey);
    }

    public static function formatLastFour(string $secret): string
    {
        return Str::substr($secret, -4);
    }
}

