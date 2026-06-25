<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;

class SyncServerStatsCommand extends Command
{
    protected $signature = 'dgen:server:sync-stats
        {--node-id= : Specific node to sync from (default: all)}
        {--dry-run : Show changes without applying them}';

    protected $description = 'Sync server resource usage statistics from Wings nodes to update panel records.';

    public function handle(): int
    {
        $this->output->title('Server Stats Sync');

        $query = Node::query();

        if ($this->option('node-id')) {
            $query->where('id', (int) $this->option('node-id'));
        }

        $nodes = $query->get();

        if ($nodes->isEmpty()) {
            $this->info('No nodes found to sync from.');

            return 0;
        }

        $this->info("Syncing server stats from {$nodes->count()} node(s)...");
        $this->newLine();

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($nodes as $node) {
            $result = $this->syncNode($node);
            $totalSynced += $result['synced'];
            $totalErrors += $result['errors'];

            $this->line("  {$node->name}: <info>{$result['synced']} synced</info>, <error>{$result['errors']} errors</error>");
        }

        $this->newLine();
        $this->info("Sync complete: $totalSynced server(s) updated, $totalErrors error(s).");

        return $totalErrors > 0 ? 1 : 0;
    }

    private function syncNode(Node $node): array
    {
        $synced = 0;
        $errors = 0;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                    'Accept' => 'application/json',
                ])
                ->get($node->getConnectionAddress() . '/api/servers');

            if (!$response->successful()) {
                return ['synced' => 0, 'errors' => 1];
            }

            $serverData = $response->json('data', []);

            foreach ($serverData as $data) {
                try {
                    $this->syncServerStats($node, $data);
                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::warning('Failed to sync server stats', [
                        'node_id' => $node->id,
                        'server_identifier' => $data['identifier'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch server list from node', [
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ]);

            return ['synced' => 0, 'errors' => 1];
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    private function syncServerStats(Node $node, array $data): void
    {
        $uuid = $data['uuid'] ?? null;

        if (!$uuid) {
            return;
        }

        $server = Server::where('uuid', $uuid)->first();

        if (!$server) {
            return;
        }

        $stats = $data['stats'] ?? [];

        if (empty($stats)) {
            return;
        }

        $updateData = [
            'updated_at' => CarbonImmutable::now(),
        ];

        if (isset($stats['cpu_absolute'])) {
            $updateData['cpu'] = (int) round($stats['cpu_absolute']);
        }

        if (isset($stats['memory_bytes'])) {
            $updateData['memory'] = (int) round($stats['memory_bytes'] / 1024 / 1024);
        }

        if (isset($stats['disk_bytes'])) {
            $updateData['disk'] = (int) round($stats['disk_bytes'] / 1024 / 1024);
        }

        if (isset($stats['network_rx_bytes'])) {
            $updateData['network_rx'] = (int) $stats['network_rx_bytes'];
        }

        if (isset($stats['network_tx_bytes'])) {
            $updateData['network_tx'] = (int) $stats['network_tx_bytes'];
        }

        if (!$this->option('dry-run')) {
            $server->update($updateData);
        }
    }
}
