<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class NodeStatusController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $node = $server->node;

            $status = [
                'node' => [
                    'id' => $node->id,
                    'name' => $node->name,
                    'fqdn' => $node->fqdn,
                    'scheme' => $node->scheme,
                    'maintenance_mode' => $node->maintenance_mode,
                    'is_online' => $this->checkNodeOnline($node),
                ],
                'resources' => [
                    'memory' => [
                        'total' => $node->memory,
                        'used' => $node->servers()->sum('memory'),
                        'overallocate' => $node->memory_overallocate,
                    ],
                    'disk' => [
                        'total' => $node->disk,
                        'used' => $node->servers()->sum('disk'),
                        'overallocate' => $node->disk_overallocate,
                    ],
                    'servers' => [
                        'total' => $node->servers()->count(),
                        'running' => $node->servers()->where('status', '!=', 'suspended')->count(),
                    ],
                ],
                'location' => [
                    'id' => $node->location->id ?? null,
                    'name' => $node->location->name ?? 'Unknown',
                ],
            ];

            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get node status: ' . $e->getMessage()], 500);
        }
    }

    private function checkNodeOnline($node): bool
    {
        try {
            $response = Http::withToken($node->daemon_token)
                ->timeout(5)
                ->get($node->scheme . '://' . $node->fqdn . ':' . $node->daemonListen . '/api/servers');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
