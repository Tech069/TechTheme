<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;

class CrossVpsCacheInvalidationService
{
    private const INVALIDATION_TIMEOUT = 10;

    private const CACHE_PREFIXES_TO_INVALIDATE = [
        'server:',
        'node:',
        'allocation:',
        'user:',
        'egg:',
        'nest:',
        'addon_config:',
        'subdomain:',
        'reverse_proxy:',
    ];

    public function __construct()
    {
    }

    /**
     * Invalidate cache across all VPS nodes for a specific key pattern.
     */
    public function invalidate(string $pattern): array
    {
        $results = ['success' => [], 'failed' => []];

        $nodes = Node::query()->where('maintenance_mode', false)->get();

        foreach ($nodes as $node) {
            try {
                $this->invalidateOnNode($node, $pattern);
                $results['success'][] = $node->id;
            } catch (\Exception $exception) {
                Log::warning('Failed to invalidate cache on node', [
                    'node_id' => $node->id,
                    'pattern' => $pattern,
                    'error' => $exception->getMessage(),
                ]);
                $results['failed'][] = $node->id;
            }
        }

        return $results;
    }

    /**
     * Invalidate cache on a specific node.
     */
    public function invalidateOnNode(Node $node, string $pattern): bool
    {
        $url = $node->getConnectionAddress() . '/api/cache/invalidate';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                'Content-Type' => 'application/json',
            ])->timeout(self::INVALIDATION_TIMEOUT)
                ->post($url, ['pattern' => $pattern]);

            return $response->successful();
        } catch (\Exception $exception) {
            Log::error('Cache invalidation HTTP request failed', [
                'node_id' => $node->id,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    /**
     * Invalidate all standard cache prefixes across all nodes.
     */
    public function invalidateAll(): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach (self::CACHE_PREFIXES_TO_INVALIDATE as $prefix) {
            $result = $this->invalidate($prefix);
            $results['success'] = array_merge($results['success'], $result['success']);
            $results['failed'] = array_merge($results['failed'], $result['failed']);
        }

        return $results;
    }

    /**
     * Invalidate cache for a specific server across all nodes.
     */
    public function invalidateServer(int $serverId): array
    {
        return $this->invalidate("server:$serverId");
    }

    /**
     * Invalidate cache for a specific node.
     */
    public function invalidateNode(int $nodeId): bool
    {
        $node = Node::findOrFail($nodeId);

        return $this->invalidateOnNode($node, "node:$nodeId");
    }

    /**
     * Flush local application cache.
     */
    public function flushLocal(): void
    {
        foreach (self::CACHE_PREFIXES_TO_INVALIDATE as $prefix) {
            Cache::tags([$prefix])->flush();
        }
    }
}
