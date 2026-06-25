<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Node;

class NodeStatusService
{
    private const STATUS_CACHE_PREFIX = 'node_status:';

    private const STATUS_CACHE_TTL = 30;

    private const ALERT_THRESHOLDS = [
        'cpu_percent' => 90,
        'memory_percent' => 90,
        'disk_percent' => 90,
        'load_average' => 10,
    ];

    public function __construct()
    {
    }

    /**
     * Get the status of a node from its Wings agent.
     */
    public function getNodeStatus(Node $node): ?array
    {
        $cacheKey = self::STATUS_CACHE_PREFIX . $node->id;

        return Cache::remember($cacheKey, self::STATUS_CACHE_TTL, function () use ($node) {
            try {
                $url = $node->getConnectionAddress() . '/api/system';

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                ])->timeout(10)->get($url);

                if (!$response->successful()) {
                    Log::warning('Node status request failed', [
                        'node_id' => $node->id,
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $data = $response->json();

                return [
                    'node_id' => $node->id,
                    'name' => $node->name,
                    'online' => true,
                    'cpu' => $this->parseCpuUsage($data),
                    'memory' => $this->parseMemoryUsage($data, $node),
                    'disk' => $this->parseDiskUsage($data, $node),
                    'load_average' => $data['load'] ?? [0, 0, 0],
                    'uptime' => $data['uptime'] ?? 0,
                    'system_info' => $data['system'] ?? [],
                    'server_count' => $node->servers()->count(),
                    'timestamp' => now()->toISOString(),
                ];
            } catch (\Exception $exception) {
                Log::error('Failed to get node status', [
                    'node_id' => $node->id,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get status of all nodes.
     */
    public function getAllNodesStatus(): array
    {
        $nodes = Node::all();
        $statuses = [];

        foreach ($nodes as $node) {
            $status = $this->getNodeStatus($node);
            $statuses[] = $status ?? [
                'node_id' => $node->id,
                'name' => $node->name,
                'online' => false,
                'error' => 'Could not reach node',
            ];
        }

        return $statuses;
    }

    /**
     * Check if a node needs attention (high resource usage).
     */
    public function checkAlerts(Node $node): array
    {
        $status = $this->getNodeStatus($node);
        $alerts = [];

        if (!$status || !$status['online']) {
            $alerts[] = [
                'type' => 'offline',
                'severity' => 'critical',
                'message' => "Node {$node->name} is offline",
            ];

            return $alerts;
        }

        if (($status['cpu']['percent'] ?? 0) > self::ALERT_THRESHOLDS['cpu_percent']) {
            $alerts[] = [
                'type' => 'high_cpu',
                'severity' => 'warning',
                'message' => sprintf('Node %s CPU usage is %.1f%%', $node->name, $status['cpu']['percent']),
            ];
        }

        if (($status['memory']['percent'] ?? 0) > self::ALERT_THRESHOLDS['memory_percent']) {
            $alerts[] = [
                'type' => 'high_memory',
                'severity' => 'warning',
                'message' => sprintf('Node %s memory usage is %.1f%%', $node->name, $status['memory']['percent']),
            ];
        }

        if (($status['disk']['percent'] ?? 0) > self::ALERT_THRESHOLDS['disk_percent']) {
            $alerts[] = [
                'type' => 'high_disk',
                'severity' => 'warning',
                'message' => sprintf('Node %s disk usage is %.1f%%', $node->name, $status['disk']['percent']),
            ];
        }

        return $alerts;
    }

    /**
     * Parse CPU usage from the response.
     */
    private function parseCpuUsage(array $data): array
    {
        $cpuCount = $data['cpu_count'] ?? 1;
        $cpuInfo = $data['cpu'] ?? [];

        $used = $cpuInfo['usage'] ?? 0;
        $percent = $cpuCount > 0 ? ($used / $cpuCount) * 100 : 0;

        return [
            'count' => $cpuCount,
            'percent' => round($percent, 1),
            'used' => round($used, 2),
        ];
    }

    /**
     * Parse memory usage from the response.
     */
    private function parseMemoryUsage(array $data, Node $node): array
    {
        $total = ($node->memory ?? 0) * 1024 * 1024;
        $used = $data['memory']['used'] ?? 0;
        $percent = $total > 0 ? ($used / $total) * 100 : 0;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $total - $used,
            'percent' => round($percent, 1),
        ];
    }

    /**
     * Parse disk usage from the response.
     */
    private function parseDiskUsage(array $data, Node $node): array
    {
        $total = ($node->disk ?? 0) * 1024 * 1024;
        $used = $data['disk']['used'] ?? 0;
        $percent = $total > 0 ? ($used / $total) * 100 : 0;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $total - $used,
            'percent' => round($percent, 1),
        ];
    }

    /**
     * Force refresh status for a node.
     */
    public function refreshStatus(Node $node): ?array
    {
        Cache::forget(self::STATUS_CACHE_PREFIX . $node->id);

        return $this->getNodeStatus($node);
    }
}
