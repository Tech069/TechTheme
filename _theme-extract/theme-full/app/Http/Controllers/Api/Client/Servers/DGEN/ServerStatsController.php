<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class ServerStatsController extends Controller
{
    public function batch(Request $request, Server $server): JsonResponse
    {
        try {
            $node = $server->node;

            $stats = [
                'server' => [
                    'id' => $server->id,
                    'name' => $server->name,
                    'status' => $server->status,
                    'memory' => [
                        'total' => $server->memory,
                        'used' => $this->getServerMemoryUsage($server),
                        'percentage' => $server->memory > 0 ? round(($this->getServerMemoryUsage($server) / $server->memory) * 100, 1) : 0,
                    ],
                    'disk' => [
                        'total' => $server->disk,
                        'used' => $this->getDirectorySize($server->server_data_directory),
                        'percentage' => $server->disk > 0 ? round(($this->getDirectorySize($server->server_data_directory) / $server->disk) * 100, 1) : 0,
                    ],
                    'cpu' => [
                        'total' => $server->cpu,
                        'used' => 0,
                        'percentage' => 0,
                    ],
                    'io' => $server->io,
                    'uptime' => $this->getUptime($server),
                    'created_at' => $server->created_at?->toISOString(),
                ],
                'node' => [
                    'id' => $node->id,
                    'name' => $node->name,
                    'memory_total' => $node->memory,
                    'memory_used' => $node->servers()->sum('memory'),
                    'disk_total' => $node->disk,
                    'disk_used' => $node->servers()->sum('disk'),
                ],
                'allocations' => [
                    'total' => $server->allocations()->count(),
                    'primary' => $server->allocation?->ip . ':' . $server->allocation?->port,
                ],
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get stats: ' . $e->getMessage()], 500);
        }
    }

    private function getServerMemoryUsage(Server $server): int
    {
        return (int) DB::table('server_variables')
            ->where('server_id', $server->id)
            ->sum('variable_value');
    }

    private function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) return 0;
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function getUptime(Server $server): ?string
    {
        if ($server->installed_at) {
            $diff = $server->installed_at->diffForHumans();
            return $diff;
        }
        return null;
    }
}
