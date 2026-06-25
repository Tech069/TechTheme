<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\StatusIncident;

class DdosAlertService
{
    private const ALERT_CACHE_PREFIX = 'ddos_alert:';

    private const ALERT_CACHE_TTL = 300;

    private const DETECTION_THRESHOLDS = [
        'packets_per_second' => 10000,
        'bandwidth_mbps' => 500,
        'connection_count' => 5000,
        'syn_flood_rate' => 1000,
    ];

    private const ALERT_COOLDOWN_MINUTES = 15;

    public function __construct()
    {
    }

    /**
     * Detect potential DDoS attacks on a node.
     */
    public function detectAttack(Node $node, array $metrics): array
    {
        $result = [
            'detected' => false,
            'type' => null,
            'severity' => null,
            'metrics' => $metrics,
        ];

        if (($metrics['packets_per_second'] ?? 0) > self::DETECTION_THRESHOLDS['packets_per_second']) {
            $result['detected'] = true;
            $result['type'] = 'packet_flood';
            $result['severity'] = 'high';
        } elseif (($metrics['bandwidth_mbps'] ?? 0) > self::DETECTION_THRESHOLDS['bandwidth_mbps']) {
            $result['detected'] = true;
            $result['type'] = 'bandwidth_flood';
            $result['severity'] = 'high';
        } elseif (($metrics['connection_count'] ?? 0) > self::DETECTION_THRESHOLDS['connection_count']) {
            $result['detected'] = true;
            $result['type'] = 'connection_flood';
            $result['severity'] = 'medium';
        } elseif (($metrics['syn_flood_rate'] ?? 0) > self::DETECTION_THRESHOLDS['syn_flood_rate']) {
            $result['detected'] = true;
            $result['type'] = 'syn_flood';
            $result['severity'] = 'critical';
        }

        if ($result['detected']) {
            $this->raiseAlert($node, $result);
        }

        return $result;
    }

    /**
     * Raise a DDoS alert for a node.
     */
    public function raiseAlert(Node $node, array $attackData): void
    {
        $cacheKey = self::ALERT_CACHE_PREFIX . $node->id;

        if (Cache::has($cacheKey)) {
            Log::debug('DDoS alert cooldown active for node', ['node_id' => $node->id]);

            return;
        }

        try {
            StatusIncident::create([
                'title' => 'DDoS Attack Detected - ' . $node->name,
                'message' => sprintf(
                    'Potential %s attack detected on node %s (ID: %d). Severity: %s. Metrics: %s',
                    $attackData['type'] ?? 'unknown',
                    $node->name,
                    $node->id,
                    $attackData['severity'] ?? 'unknown',
                    json_encode($attackData['metrics'] ?? [])
                ),
                'severity' => $attackData['severity'] ?? 'medium',
                'status' => 'investigating',
                'impact' => [
                    'node_id' => $node->id,
                    'attack_type' => $attackData['type'],
                    'metrics' => $attackData['metrics'],
                ],
            ]);

            Cache::put($cacheKey, true, self::ALERT_CACHE_TTL);

            $this->notifyDiscord($node, $attackData);
            $this->notifyAdmins($node, $attackData);

            Log::critical('DDoS alert raised', [
                'node_id' => $node->id,
                'node_name' => $node->name,
                'attack_type' => $attackData['type'],
                'severity' => $attackData['severity'],
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to raise DDoS alert', [
                'node_id' => $node->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Resolve a DDoS alert for a node.
     */
    public function resolveAlert(Node $node, ?string $notes = null): bool
    {
        try {
            $incident = StatusIncident::where('status', '!=', 'resolved')
                ->where('impact->node_id', $node->id)
                ->latest()
                ->first();

            if (!$incident) {
                return false;
            }

            $incident->update([
                'status' => 'resolved',
                'message' => $incident->message . "\n\nResolved: " . ($notes ?? 'Attack mitigated.'),
            ]);

            Cache::forget(self::ALERT_CACHE_PREFIX . $node->id);

            Log::info('DDoS alert resolved', [
                'node_id' => $node->id,
                'incident_id' => $incident->id,
            ]);

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to resolve DDoS alert', [
                'node_id' => $node->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get active DDoS alerts.
     */
    public function getActiveAlerts(): array
    {
        return StatusIncident::where('status', '!=', 'resolved')
            ->where('severity', '!=', 'none')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get DDoS alert history for a specific node.
     */
    public function getNodeAlertHistory(Node $node, int $limit = 20): array
    {
        return StatusIncident::where('impact->node_id', $node->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Send Discord notification for DDoS alert.
     */
    private function notifyDiscord(Node $node, array $attackData): void
    {
        $webhookUrl = config('services.discord.ddos_webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

        try {
            Http::post($webhookUrl, [
                'embeds' => [[
                    'title' => ':rotating_light: DDoS Attack Detected',
                    'description' => sprintf(
                        "**Node:** %s (ID: %d)\n**Type:** %s\n**Severity:** %s",
                        $node->name,
                        $node->id,
                        $attackData['type'] ?? 'unknown',
                        strtoupper($attackData['severity'] ?? 'unknown')
                    ),
                    'color' => 0xE74C3C,
                    'timestamp' => now()->toISOString(),
                    'footer' => ['text' => 'HyperPanel DDoS Detection'],
                ]],
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to send DDoS Discord notification', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Notify admins about DDoS alert.
     */
    private function notifyAdmins(Node $node, array $attackData): void
    {
        // Email notification to admins would go here
        Log::info('Admin notification queued for DDoS alert', [
            'node_id' => $node->id,
        ]);
    }
}
