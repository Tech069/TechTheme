<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSplit;

class SyncServerSplitsCommand extends Command
{
    protected $signature = 'dgen:server:sync-splits
        {--dry-run : Show changes without applying them}
        {--server-id= : Specific server ID to sync splits for}';

    protected $description = 'Synchronize server split allocations to ensure consistency between master and sub-servers.';

    public function handle(): int
    {
        $this->output->title('Server Split Sync');

        $query = ServerSplit::query()
            ->with(['masterServer', 'subServer']);

        if ($this->option('server-id')) {
            $serverId = (int) $this->option('server-id');
            $query->where('master_server_id', $serverId)
                ->orWhere('sub_server_id', $serverId);
        }

        $splits = $query->get();

        if ($splits->isEmpty()) {
            $this->info('No server splits found.');

            return 0;
        }

        $this->info("Checking {$splits->count()} server split(s)...");
        $this->newLine();

        $fixedCount = 0;
        $errorCount = 0;

        foreach ($splits as $split) {
            $issues = $this->validateSplit($split);

            if (empty($issues)) {
                $this->line("  <info>OK</info>: Split #{$split->id} ({$split->sub_server_name})");
                continue;
            }

            $this->error("  ISSUES in Split #{$split->id} ({$split->sub_server_name}):");

            foreach ($issues as $issue) {
                $this->line("    - $issue");
            }

            if (!$this->option('dry-run')) {
                try {
                    $this->fixSplit($split, $issues);
                    $fixedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("    Failed to fix: " . $e->getMessage());
                    Log::error('Server split sync failed', [
                        'split_id' => $split->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] No changes applied.');
        } else {
            $this->info("Sync complete: $fixedCount fixed, $errorCount failed.");
        }

        return $errorCount > 0 ? 1 : 0;
    }

    private function validateSplit(ServerSplit $split): array
    {
        $issues = [];

        if (!$split->masterServer) {
            $issues[] = "Master server #{$split->master_server_id} no longer exists";
        }

        if (!$split->subServer) {
            $issues[] = "Sub server #{$split->sub_server_id} no longer exists";
        }

        if ($split->masterServer && $split->subServer) {
            if ($split->master_server_id === $split->sub_server_id) {
                $issues[] = 'Master and sub server are the same';
            }

            if ($split->allocated_memory > $split->masterServer->memory) {
                $issues[] = "Allocated memory ({$split->allocated_memory}MB) exceeds master server limit ({$split->masterServer->memory}MB)";
            }

            if ($split->allocated_cpu > $split->masterServer->cpu) {
                $issues[] = "Allocated CPU ({$split->allocated_cpu}%) exceeds master server limit ({$split->masterServer->cpu}%)";
            }

            if ($split->allocated_disk > $split->masterServer->disk) {
                $issues[] = "Allocated disk ({$split->allocated_disk}MB) exceeds master server limit ({$split->masterServer->disk}MB)";
            }

            if ($split->subServer->status === 'suspended') {
                $issues[] = 'Sub-server is currently suspended';
            }

            $totalSplits = ServerSplit::where('master_server_id', $split->master_server_id)->sum('allocated_memory');
            if ($totalSplits > $split->masterServer->memory) {
                $issues[] = 'Total allocated memory across all splits exceeds master server memory';
            }
        }

        return $issues;
    }

    private function fixSplit(ServerSplit $split, array $issues): void
    {
        foreach ($issues as $issue) {
            if (str_contains($issue, 'no longer exists') && str_contains($issue, 'Sub server')) {
                $split->delete();
                $this->line("    <info>Deleted</info>: Orphaned split record removed");
                return;
            }

            if (str_contains($issue, 'no longer exists') && str_contains($issue, 'Master server')) {
                $split->delete();
                $this->line("    <info>Deleted</info>: Orphaned split record removed");
                return;
            }

            if (str_contains($issue, 'exceeds master server limit')) {
                if (preg_match('/Allocated (\w+) \((\d+)(MB|%)\) exceeds master server limit \((\d+)(MB|%)\)/', $issue, $matches)) {
                    $metric = $matches[1];
                    $limit = (int) $matches[4];

                    $column = match ($metric) {
                        'memory' => 'allocated_memory',
                        'CPU' => 'allocated_cpu',
                        'disk' => 'allocated_disk',
                        default => null,
                    };

                    if ($column) {
                        $split->update([$column => $limit]);
                        $this->line("    <info>Fixed</info>: $metric capped at $limit");
                    }
                }
            }
        }
    }
}
