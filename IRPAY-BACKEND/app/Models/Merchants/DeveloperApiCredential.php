<?php

namespace App\Models\Merchants;

use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DeveloperApiCredential extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'merchant_id'   => 'integer',
        'name'          => 'string',
        'client_id'     => 'string',
        'client_secret' => 'string',
        'mode'          => 'string',
        'status'        => 'boolean',
        'last_used_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public static function defaultScopes(): array
    {
        return config('developer-api.scopes', ['default']);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(DeveloperApiCredentialSecret::class, 'developer_api_credential_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeveloperApiCredentialLog::class, 'developer_api_credential_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeAuth($query)
    {
        $merchantId = auth()->id();

        if (!$merchantId && function_exists('authGuardApi')) {
            $guardInfo = authGuardApi();
            $guardName = $guardInfo['guard'] ?? null;

            if ($guardName) {
                $merchantId = auth()->guard($guardName)->id();
            }
        }

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        return $query;
    }

    public function activeSecret(string $scope): ?DeveloperApiCredentialSecret
    {
        return $this->secrets()->active()->where('scope', $scope)->latest('id')->first();
    }

    public function activeSecrets(): array
    {
        return $this->secrets()
            ->active()
            ->get()
            ->groupBy('scope')
            ->map(function ($secrets) {
                return $secrets->sortByDesc('id')->first();
            })->all();
    }

    public function issueSecret(string $scope, ?string $plainSecret = null, bool $logActivity = true, ?string $actorType = null, ?int $actorId = null): array
    {
        $plainSecret = $plainSecret ?? DeveloperApiCredentialSecret::generateSecret();
        $secret = $this->secrets()->create([
            'scope' => $scope,
            'secret_identifier' => DeveloperApiCredentialSecret::buildIdentifier($plainSecret),
            'secret_last_four' => DeveloperApiCredentialSecret::formatLastFour($plainSecret),
        ]);

        if (blank($this->client_secret)) {
            $this->forceFill(['client_secret' => $secret->secret_identifier])->save();
        }

        if ($logActivity) {
            $this->logActivity('secret_issued', [
                'scope' => $scope,
                'secret_id' => $secret->id,
            ], $actorType, $actorId);
        }

        return [$secret, $plainSecret];
    }

    public function rotateSecret(string $scope, ?string $plainSecret = null, ?string $actorType = null, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($scope, $plainSecret, $actorType, $actorId) {
            $current = $this->secrets()->active()->where('scope', $scope)->get();
            foreach ($current as $secret) {
                $secret->markRotated();
            }

            [$secret, $issuedSecret] = $this->issueSecret($scope, $plainSecret, false, $actorType, $actorId);

            $this->forceFill(['client_secret' => $secret->secret_identifier])->save();

            $this->logActivity('secret_rotated', [
                'scope' => $scope,
                'secret_id' => $secret->id,
                'previous_secret_ids' => $current->pluck('id')->all(),
            ], $actorType, $actorId);

            return [$secret, $issuedSecret];
        });
    }

    public function revokeScope(string $scope, ?string $actorType = null, ?int $actorId = null): ?DeveloperApiCredentialSecret
    {
        $secret = $this->secrets()->active()->where('scope', $scope)->latest('id')->first();
        if (!$secret) {
            return null;
        }

        $secret->revoke();

        $this->logActivity('scope_revoked', [
            'scope' => $scope,
            'secret_id' => $secret->id,
        ], $actorType, $actorId);

        return $secret;
    }

    public function markSecretUsage(DeveloperApiCredentialSecret $secret, ?string $actorType = null, ?int $actorId = null): void
    {
        $secret->touchLastUsed();
        $this->forceFill([
            'last_used_at' => now(),
            'client_secret' => $secret->secret_identifier,
        ])->save();

        $this->logActivity('secret_used', [
            'scope' => $secret->scope,
            'secret_id' => $secret->id,
        ], $actorType, $actorId);
    }

    public function pruneRevokedSecrets(int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);

        $count = $this->secrets()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<=', $cutoff)
            ->delete();

        if ($count > 0) {
            $this->logActivity('secrets_pruned', [
                'count' => $count,
                'cutoff' => $cutoff->toDateTimeString(),
            ], 'system');
        }

        return $count;
    }

    public function logActivity(string $action, array $meta = [], ?string $actorType = null, ?int $actorId = null): DeveloperApiCredentialLog
    {
        $meta = Arr::sortRecursive($meta);

        return $this->logs()->create([
            'action' => $action,
            'meta' => $meta,
            'performed_by_type' => $actorType,
            'performed_by_id' => $actorId,
        ]);
    }

    public static function provisionForMerchant(Merchant $merchant, string $name): array
    {
        return DB::transaction(function () use ($merchant, $name) {
            $credential = self::create([
                'merchant_id' => $merchant->id,
                'name' => $name,
                'client_id' => generate_unique_string('developer_api_credentials', 'client_id', 100),
                'client_secret' => '',
                'mode' => PaymentGatewayConst::ENV_SANDBOX,
                'status' => true,
                'created_at' => now(),
            ]);

            $secrets = [];
            foreach (self::defaultScopes() as $index => $scope) {
                [$secretModel, $plainSecret] = $credential->issueSecret($scope, null, false, 'merchant', $merchant->id);
                $secrets[$scope] = $plainSecret;

                if ($index === 0) {
                    $credential->forceFill(['client_secret' => $secretModel->secret_identifier])->save();
                }
            }

            $credential->logActivity('provisioned', [
                'scopes' => array_keys($secrets),
            ], 'merchant', $merchant->id);

            return [$credential->fresh(['secrets']), $secrets];
        });
    }
}

