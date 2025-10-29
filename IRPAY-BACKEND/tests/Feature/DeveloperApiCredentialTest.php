<?php

namespace Tests\Feature;

use App\Models\Merchants\DeveloperApiCredential;
use App\Models\Merchants\DeveloperApiCredentialSecret;
use App\Models\Merchants\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeveloperApiCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_scoped_secrets_and_logs_activity(): void
    {
        $merchant = Merchant::factory()->create();

        [$credential, $issuedSecrets] = DeveloperApiCredential::provisionForMerchant($merchant, 'Checkout API');

        $this->assertNotEmpty($issuedSecrets);
        $this->assertEquals(count(DeveloperApiCredential::defaultScopes()), $credential->secrets()->count());

        foreach ($issuedSecrets as $scope => $plaintext) {
            $secret = $credential->activeSecret($scope);
            $this->assertNotNull($secret);
            $this->assertSame(
                DeveloperApiCredentialSecret::buildIdentifier($plaintext),
                $secret->secret_identifier
            );
            $this->assertNotSame($plaintext, $secret->secret_identifier);
        }

        $this->assertEquals(
            'provisioned',
            $credential->logs()->latest()->first()->action
        );
    }

    public function test_rotation_revokes_previous_secret_and_records_log(): void
    {
        $merchant = Merchant::factory()->create();
        [$credential] = DeveloperApiCredential::provisionForMerchant($merchant, 'Rotation Test');

        $initialSecret = $credential->activeSecret(DeveloperApiCredential::defaultScopes()[0]);
        $this->assertNotNull($initialSecret);

        [$newSecret, $plaintext] = $credential->rotateSecret($initialSecret->scope, null, 'merchant', $merchant->id);

        $initialSecret->refresh();
        $this->assertNotNull($initialSecret->revoked_at);
        $this->assertNotNull($initialSecret->rotated_at);
        $this->assertSame(
            DeveloperApiCredentialSecret::buildIdentifier($plaintext),
            $newSecret->secret_identifier
        );
        $this->assertEquals('secret_rotated', $credential->logs()->latest()->first()->action);
    }

    public function test_usage_updates_last_used_timestamps_and_logs(): void
    {
        $merchant = Merchant::factory()->create();
        [$credential] = DeveloperApiCredential::provisionForMerchant($merchant, 'Usage Test');

        $scope = DeveloperApiCredential::defaultScopes()[0];
        $secret = $credential->activeSecret($scope);
        $this->assertNull($credential->last_used_at);
        $this->assertNull($secret->last_used_at);

        $credential->markSecretUsage($secret, 'system');

        $secret->refresh();
        $credential->refresh();

        $this->assertNotNull($secret->last_used_at);
        $this->assertNotNull($credential->last_used_at);
        $this->assertEquals('secret_used', $credential->logs()->latest()->first()->action);
    }

    public function test_scope_revocation_and_pruning_flow(): void
    {
        $merchant = Merchant::factory()->create();
        [$credential] = DeveloperApiCredential::provisionForMerchant($merchant, 'Prune Test');

        $scope = DeveloperApiCredential::defaultScopes()[0];
        $secret = $credential->activeSecret($scope);

        $credential->revokeScope($scope, 'merchant', $merchant->id);
        $secret->refresh();
        $this->assertNotNull($secret->revoked_at);
        $this->assertEquals('scope_revoked', $credential->logs()->latest()->first()->action);

        // Create another revoked secret old enough for pruning
        $credential->secrets()
            ->where('id', $secret->id)
            ->update(['revoked_at' => Carbon::now()->subDays(10)]);

        $this->artisan('developer-api:prune', ['--days' => 5])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('developer_api_credential_secrets', ['id' => $secret->id]);
        $this->assertTrue(
            $credential->logs()->where('action', 'secrets_pruned')->exists()
        );
    }
}

