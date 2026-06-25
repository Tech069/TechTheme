<?php

namespace Pterodactyl\Console\Commands\Server;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Services\SubdomainManager\CloudflareService;

class CleanupOrphanedSubdomainsCommand extends Command
{
    protected $signature = 'p:server:cleanup-orphaned-subdomains
        {--dry-run : Show what would be cleaned up without applying changes}
        {--remove-cloudflare : Also remove orphaned DNS records from Cloudflare}';

    protected $description = 'Clean up orphaned subdomain records that reference servers or allocations that no longer exist.';

    public function handle(): int
    {
        $this->output->title('Orphaned Subdomain Cleanup');

        $orphanedSubdomains = ServerSubdomain::query()
            ->whereDoesntHave('server')
            ->get();

        if ($orphanedSubdomains->isEmpty()) {
            $this->info('No orphaned subdomains found.');

            return 0;
        }

        $this->info("Found {$orphanedSubdomains->count()} orphaned subdomain record(s):");
        $this->newLine();

        $this->table(
            ['ID', 'Subdomain', 'Domain', 'Server ID', 'Created At'],
            $orphanedSubdomains->map(fn ($s) => [
                $s->id,
                $s->subdomain,
                $s->domain,
                $s->server_id,
                $s->created_at?->toDateTimeString() ?? 'N/A',
            ])
        );

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('[DRY-RUN] No subdomains were removed.');

            return 0;
        }

        if (!$this->confirm("Clean up {$orphanedSubdomains->count()} orphaned subdomain(s)?", true)) {
            $this->info('Cleanup cancelled.');

            return 0;
        }

        $cleanedCount = 0;
        $failedCount = 0;

        foreach ($orphanedSubdomains as $subdomain) {
            try {
                if ($this->option('remove-cloudflare') && $subdomain->cloudflare_record) {
                    $this->removeCloudflareRecord($subdomain);
                }

                $subdomain->delete();
                $cleanedCount++;

                $this->line("  <info>Removed</info>: {$subdomain->subdomain}.{$subdomain->domain}");
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("  Failed to remove {$subdomain->subdomain}.{$subdomain->domain}: " . $e->getMessage());
                Log::error('Subdomain cleanup failed', [
                    'subdomain_id' => $subdomain->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Cleanup complete: $cleanedCount removed, $failedCount failed.");

        return $failedCount > 0 ? 1 : 0;
    }

    private function removeCloudflareRecord(ServerSubdomain $subdomain): void
    {
        try {
            $cloudflareService = app(CloudflareService::class);
            $recordData = json_decode($subdomain->cloudflare_record, true);

            if ($recordData && isset($recordData['record_id'])) {
                $cloudflareService->deleteDnsRecord($recordData['zone_id'] ?? null, $recordData['record_id']);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to remove Cloudflare DNS record', [
                'subdomain' => $subdomain->subdomain,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
