<?php

namespace App\Console\Commands;

use App\Models\Merchants\DeveloperApiCredential;
use Illuminate\Console\Command;

class PruneDeveloperApiSecrets extends Command
{
    protected $signature = 'developer-api:prune {--days= : Override the default retention window in days}';

    protected $description = 'Prune revoked developer API secrets and rotation history records.';

    public function handle(): int
    {
        $retention = (int) ($this->option('days') ?? config('developer-api.secret_retention_days'));
        $this->info("Pruning revoked developer API secrets older than {$retention} days...");

        $deleted = 0;
        DeveloperApiCredential::with('secrets')->chunk(100, function ($credentials) use (&$deleted, $retention) {
            foreach ($credentials as $credential) {
                $deleted += $credential->pruneRevokedSecrets($retention);
            }
        });

        $this->info("Pruned {$deleted} revoked secret(s).");
        return Command::SUCCESS;
    }
}

