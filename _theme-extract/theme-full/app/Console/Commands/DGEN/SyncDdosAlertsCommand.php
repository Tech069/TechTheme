<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;

class SyncDdosAlertsCommand extends Command
{
    protected $signature = 'dgen:ddos:sync-alerts
        {--node-id= : Specific node ID to sync from (default: all nodes)}
        {--hours=24 : How many hours back to look for alerts}
        {--notify : Send Discord notifications for new alerts}';

    protected $description = 'Sync DDoS alert events from Wings nodes and update the panel database.';

    public function handle(): int
    {
        $this->output->title('DDoS Alert Sync');

        $hours = (int) $this->option('hours');
        $nodeId = $this->option('node-id') ? (int) $this->option('node-id') : null;

        $query = Node::query();

        if ($nodeId) {
            $query->where('id', $nodeId);
        }

        $nodes = $query->get();

        if ($nodes->isEmpty()) {
            $this->info('No nodes found to sync from.');

            return 0;
        }

        $this->info("Syncing DDoS alerts from {$nodes->count()} node(s) (last {$hours}h)...");
        $this->newLine();

        $totalNew = 0;
        $totalUpdated = 0;
        $newAlerts = [];

        foreach ($nodes as $node) {
            $result = $this->syncFromNode($node, $hours, $newAlerts);
            $totalNew += $result['new'];
            $totalUpdated += $result['updated'];

            $status = ($result['new'] > 0 || $result['updated'] > 0)
                ? "<info>{$result['new']} new, {$result['updated']} updated</info>"
                : '<comment>No changes</comment>';

            $this->line("  {$node->name}: $status");
        }

        $this->newLine();
        $this->info("Sync complete: $totalNew new alert(s), $totalUpdated updated.");

        if (!empty($newAlerts) && $this->option('notify')) {
            $this->sendNewAlertNotifications($newAlerts);
        }

        return 0;
    }

    private function syncFromNode(Node $node, int $hours, array &$newAlerts): array
    {
        $newCount = 0;
        $updatedCount = 0;

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                    'Accept' => 'application/json',
                ])
                ->get($node->getConnectionAddress() . '/api/dgen/ddos/alerts', [
                    'since' => CarbonImmutable::now()->subHours($hours)->toIso8601String(),
                ]);

            if (!$response->successful()) {
                $this->error("  Failed to fetch alerts from {$node->name}: HTTP " . $response->status());
                return ['new' => 0, 'updated' => 0];
            }

            $alerts = $response->json('data', []);

            foreach ($alerts as $alert) {
                $result = $this->upsertAlert($alert, $node);
                if ($result === 'new') {
                    $newCount++;
                    $newAlerts[] = $alert;
                } elseif ($result === 'updated') {
                    $updatedCount++;
                }
            }
        } catch (\Exception $e) {
            Log::error('DDoS alert sync failed', [
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ]);
        }

        return ['new' => $newCount, 'updated' => $updatedCount];
    }

    private function upsertAlert(array $alert, Node $node): string
    {
        $attackHash = $alert['attack_hash'] ?? null;

        if (!$attackHash) {
            return 'skipped';
        }

        $existing = DB::table('ddos_alert_events')
            ->where('attack_hash', $attackHash)
            ->first();

        $data = [
            'attack_hash' => $attackHash,
            'host' => $alert['host'] ?? 'unknown',
            'status' => $alert['status'] ?? 'unknown',
            'reason' => $alert['reason'] ?? null,
            'peak_bps' => $alert['peak_bps'] ?? 0,
            'peak_pps' => $alert['peak_pps'] ?? 0,
            'started_at' => $alert['started_at'] ?? null,
            'ended_at' => $alert['ended_at'] ?? null,
            'first_seen_at' => $alert['first_seen_at'] ?? null,
            'last_seen_at' => $alert['last_seen_at'] ?? null,
            'raw_payload' => json_encode($alert),
            'updated_at' => CarbonImmutable::now(),
        ];

        if ($existing) {
            DB::table('ddos_alert_events')
                ->where('attack_hash', $attackHash)
                ->update($data);

            return 'updated';
        }

        $data['created_at'] = CarbonImmutable::now();
        $data['node_id'] = $node->id;

        DB::table('ddos_alert_events')->insert($data);

        return 'new';
    }

    private function sendNewAlertNotifications(array $newAlerts): void
    {
        $webhookUrl = config('dgen.discord.webhook_url');

        if (!$webhookUrl) {
            $this->warn('No Discord webhook URL configured. Skipping notifications.');
            return;
        }

        $description = '';
        foreach (array_slice($newAlerts, 0, 10) as $alert) {
            $host = $alert['host'] ?? 'unknown';
            $reason = $alert['reason'] ?? 'Unknown reason';
            $peakBps = number_format(($alert['peak_bps'] ?? 0) / 1000000, 2) . ' Mbps';
            $description .= "• **{$host}**: {$reason} (Peak: {$peakBps})\n";
        }

        if (count($newAlerts) > 10) {
            $description .= "\n...and " . (count($newAlerts) - 10) . " more.";
        }

        $payload = [
            'embeds' => [
                [
                    'title' => 'New DDoS Alert(s) Detected',
                    'description' => $description,
                    'color' => 0xFF6600,
                    'timestamp' => now()->toIso8601String(),
                    'footer' => ['text' => 'DDoS Alert Sync'],
                ],
            ],
        ];

        try {
            Http::post($webhookUrl, $payload);
            $this->info('Discord notification sent for new DDoS alerts.');
        } catch (\Exception $e) {
            $this->error('Failed to send Discord notification: ' . $e->getMessage());
        }
    }
}
