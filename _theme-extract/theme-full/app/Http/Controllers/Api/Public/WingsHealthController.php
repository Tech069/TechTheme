<?php

namespace Pterodactyl\Http\Controllers\Api\Public;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;

class WingsHealthController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $nodes = Node::all();
            $healthy = 0;
            $unhealthy = 0;
            $nodeStatuses = [];

            foreach ($nodes as $node) {
                $isOnline = false;
                try {
                    $response = Http::withToken($node->daemon_token)
                        ->timeout(5)
                        ->get($node->scheme . '://' . $node->fqdn . ':' . $node->daemonListen . '/api/servers');
                    $isOnline = $response->successful();
                } catch (\Exception $e) {
                    $isOnline = false;
                }

                if ($isOnline) {
                    $healthy++;
                } else {
                    $unhealthy++;
                }

                $nodeStatuses[] = [
                    'id' => $node->id,
                    'name' => $node->name,
                    'online' => $isOnline,
                    'fqdn' => $node->fqdn,
                ];
            }

            return response()->json([
                'status' => $unhealthy === 0 ? 'healthy' : 'degraded',
                'total_nodes' => $nodes->count(),
                'healthy' => $healthy,
                'unhealthy' => $unhealthy,
                'nodes' => $nodeStatuses,
                'checked_at' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function node(Request $request, int $nodeId): JsonResponse
    {
        try {
            $node = Node::findOrFail($nodeId);
            $isOnline = false;

            try {
                $response = Http::withToken($node->daemon_token)
                    ->timeout(5)
                    ->get($node->scheme . '://' . $node->fqdn . ':' . $node->daemonListen . '/api/servers');
                $isOnline = $response->successful();
            } catch (\Exception $e) {
                $isOnline = false;
            }

            return response()->json([
                'id' => $node->id,
                'name' => $node->name,
                'online' => $isOnline,
                'fqdn' => $node->fqdn,
                'maintenance_mode' => $node->maintenance_mode,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
