<?php

namespace Pterodactyl\Console\Commands\Server;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\SuspensionService;

class AutoSuspendCommand extends Command
{
    protected $signature = 'p:server:auto-suspend
        {--dry-run : Show what would be suspended without applying changes}
        {--notify : Send Discord notifications for suspended servers}
        {--check-databases : Also check database limits}
        {--check-backups : Also check backup limits}';

    protected $description = 'Automatically suspend servers that exceed their allocated resource limits.';

    public function handle(): int
    {
        $this->output->title('Auto-Suspend Servers');

        $servers = Server::query()
            ->whereNull('status')
            ->with('node', 'user')
            ->get();

        if ($servers->isEmpty()) {
            $this->info('No active servers found.');

            return 0;
        }

        $this->info("Checking {$servers->count()} active server(s) for limit violations...");
        $this->newLine();

        $suspendedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $suspendedServers = [];

        foreach ($servers as $server) {
            $violations = $this->checkViolations($server);

            if (empty($violations)) {
                $skippedCount++;
                continue;
            }

            $this->error("  Server #{$server->id} \"{$server->name}\":");
            foreach ($violations as $violation) {
                $this->line("    - $violation");
            }

            if (!$this->option('dry-run')) {
                try {
                    $suspensionService = app(SuspensionService::class);
                    $suspensionService->toggle($server, SuspensionService::ACTION_SUSPEND);
                    $suspendedCount++;
                    $suspendedServers[] = $server;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("    Failed to suspend: " . $e->getMessage());
                    Log::error('Auto-suspend failed', [
                        'server_id' => $server->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $suspendedCount++;
                $suspendedServers[] = $server;
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] Would suspend $suspendedCount server(s).");
        } else {
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Checked', $servers->count()],
                    ['Suspended', $suspendedCount],
                    ['Skipped (within limits)', $skippedCount],
                    ['Errors', $errorCount],
                ]
            );
        }

        if (!empty($suspendedServers) && $this->option('notify')) {
            $this->sendNotifications($suspendedServers);
        }

        return $errorCount > 0 ? 1 : 0;
    }

    private function checkViolations(Server $server): array
    {
        $violations = [];

        $node = $server->node;

        if ($node) {
            $memoryLimit = $node->memory * (1 + $node->memory_overallocate / 100);
            $diskLimit = $node->disk * (1 + $node->disk_overallocate / 100);

            $totalMemoryUsed = DB::table('servers')
                ->where('node_id', $server->node_id)
                ->whereNull('status')
                ->sum('memory');

            $totalDiskUsed = DB::table('servers')
                ->where('node_id', $server->node_id)
                ->whereNull('status')
                ->sum('disk');

            if ($totalMemoryUsed > $memoryLimit) {
                $violations[] = "Node memory limit exceeded ({$totalMemoryUsed}MB / {$memoryLimit}MB)";
            }

            if ($totalDiskUsed > $diskLimit) {
                $violations[] = "Node disk limit exceeded ({$totalDiskUsed}MB / {$diskLimit}MB)";
            }
        }

        if ($this->option('check-databases')) {
            $dbCount = $server->databases()->count();
            $dbLimit = $server->database_limit ?? 0;

            if ($dbLimit > 0 && $dbCount > $dbLimit) {
                $violations[] = "Database limit exceeded ({$dbCount} / {$dbLimit})";
            }
        }

        if ($this->option('check-backups')) {
            $backupCount = $server->backups()->where('is_successful', true)->count();
            $backupLimit = $server->backup_limit ?? 0;

            if ($backupLimit > 0 && $backupCount > $backupLimit) {
                $violations[] = "Backup limit exceeded ({$backupCount} / {$backupLimit})";
            }
        }

        if (config('server.auto_suspend.cpu_enabled', false)) {
            $cpuUsage = $this->getServerCpuUsage($server);
            $cpuLimit = config('server.auto_suspend.cpu_threshold', 100);

            if ($cpuUsage > $cpuLimit) {
                $violations[] = "CPU usage exceeded ({$cpuUsage}% / {$cpuLimit}%)";
            }
        }

        return $violations;
    }

    private function getServerCpuUsage(Server $server): float
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['Authorization' => 'Bearer ' . $server->node->getDecryptedKey()])
                ->get($server->node->getConnectionAddress() . "/api/servers/{$server->uuid}/resources");

            if ($response->successful()) {
                return $response->json('attributes.cpu_absolute', 0);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to get CPU usage for server {$server->id}: " . $e->getMessage());
        }

        return 0;
    }

    private function sendNotifications(array $suspendedServers): void
    {
        $webhookUrl = config('dgen.discord.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $description = '';
        foreach (array_slice($suspendedServers, 0, 10) as $server) {
            $description .= "• **{$server->name}** (ID: {$server->id})\n";
        }

        $payload = [
            'embeds' => [
                [
                    'title' => 'Auto-Suspended Servers',
                    'description' => $description,
                    'color' => 0xFF0000,
                    'timestamp' => now()->toIso8601String(),
                    'footer' => ['text' => 'Auto-Suspend System'],
                ],
            ],
        ];

        try {
            \Illuminate\Support\Facades\Http::post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send suspension notification', ['error' => $e->getMessage()]);
        }
    }
}
