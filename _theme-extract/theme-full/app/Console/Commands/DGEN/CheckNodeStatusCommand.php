<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;

class CheckNodeStatusCommand extends Command
{
    protected $signature = 'dgen:node:check-status
        {--timeout=5 : HTTP timeout in seconds}
        {--nodes= : Comma-separated list of node IDs to check (default: all)}
        {--notify : Send Discord notifications for unhealthy nodes}';

    protected $description = 'Check the health and connectivity status of all registered Wings nodes.';

    public function handle(): int
    {
        $this->output->title('Node Health Check');

        $nodeIds = $this->option('nodes')
            ? array_map('intval', explode(',', $this->option('nodes')))
            : [];

        $query = Node::query()->with('location');

        if (!empty($nodeIds)) {
            $query->whereIn('id', $nodeIds);
        }

        $nodes = $query->get();

        if ($nodes->isEmpty()) {
            $this->info('No nodes found to check.');

            return 0;
        }

        $this->info("Checking {$nodes->count()} node(s)...");
        $this->newLine();

        $healthyNodes = [];
        $unhealthyNodes = [];
        $maintenanceNodes = [];

        foreach ($nodes as $node) {
            $result = $this->checkNodeHealth($node);

            if ($node->maintenance_mode) {
                $maintenanceNodes[] = ['node' => $node, 'status' => $result];
                $this->line("  <comment>MAINTENANCE</comment>  {$node->name} ({$node->fqdn})");
                continue;
            }

            if ($result['healthy']) {
                $healthyNodes[] = ['node' => $node, 'status' => $result];
                $this->line("  <info>HEALTHY</info>      {$node->name} ({$node->fqdn}) — {$result['latency']}ms");
            } else {
                $unhealthyNodes[] = ['node' => $node, 'status' => $result];
                $this->error("  UNHEALTHY      {$node->name} ({$node->fqdn}) — {$result['error']}");
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Nodes', $nodes->count()],
                ['Healthy', count($healthyNodes)],
                ['Unhealthy', count($unhealthyNodes)],
                ['Maintenance', count($maintenanceNodes)],
            ]
        );

        if (!empty($unhealthyNodes) && $this->option('notify')) {
            $this->sendUnhealthyNodeNotifications($unhealthyNodes);
        }

        return !empty($unhealthyNodes) ? 1 : 0;
    }

    private function checkNodeHealth(Node $node): array
    {
        $startTime = microtime(true);

        try {
            $url = $node->getConnectionAddress() . '/api/system';

            $response = Http::timeout((int) $this->option('timeout'))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                    'Accept' => 'application/json',
                ])
                ->get($url);

            $latency = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $body = $response->json();

                return [
                    'healthy' => true,
                    'latency' => $latency,
                    'version' => $body['version'] ?? 'unknown',
                    'cpu' => $body['cpu'] ?? null,
                    'memory' => $body['memory'] ?? null,
                    'disk' => $body['disk'] ?? null,
                ];
            }

            return [
                'healthy' => false,
                'error' => 'HTTP ' . $response->status(),
                'latency' => $latency,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'healthy' => false,
                'error' => 'Connection timeout or refused',
                'latency' => null,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'latency' => null,
            ];
        }
    }

    private function sendUnhealthyNodeNotifications(array $unhealthyNodes): void
    {
        $webhookUrl = config('dgen.discord.webhook_url');

        if (!$webhookUrl) {
            $this->warn('No Discord webhook URL configured. Skipping notifications.');
            return;
        }

        $description = '';
        foreach ($unhealthyNodes as $item) {
            $node = $item['node'];
            $error = $item['status']['error'];
            $description .= "• **{$node->name}** ({$node->fqdn}): {$error}\n";
        }

        $payload = [
            'embeds' => [
                [
                    'title' => 'Unhealthy Nodes Detected',
                    'description' => $description,
                    'color' => 0xFF0000,
                    'timestamp' => now()->toIso8601String(),
                    'footer' => ['text' => 'Node Health Check'],
                ],
            ],
        ];

        try {
            Http::post($webhookUrl, $payload);
            $this->info('Discord notification sent for unhealthy nodes.');
        } catch (\Exception $e) {
            $this->error('Failed to send Discord notification: ' . $e->getMessage());
        }
    }
}
