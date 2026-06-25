<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class NetworkStatisticsController extends Controller
{
    public function allocations(Request $request, Server $server): JsonResponse
    {
        try {
            $allocations = $server->allocations()->get()->map(function ($alloc) {
                return [
                    'id' => $alloc->id,
                    'ip' => $alloc->ip,
                    'port' => $alloc->port,
                    'ip_alias' => $alloc->ip_alias,
                    'has_alias' => $alloc->has_alias,
                    'primary' => $alloc->id === $server->allocation_id,
                ];
            });

            return response()->json([
                'allocations' => $allocations,
                'primary_allocation' => $server->allocation?->ip . ':' . $server->allocation?->port,
                'total' => $allocations->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['allocations' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function portDetail(Request $request, Server $server): JsonResponse
    {
        $request->validate(['port' => 'required|integer']);

        try {
            $port = $request->input('port');
            $allocation = $server->allocations()->where('port', $port)->first();

            if (!$allocation) {
                return response()->json(['error' => 'Port not found'], 422);
            }

            return response()->json([
                'allocation' => [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'ip_alias' => $allocation->ip_alias,
                    'notes' => $allocation->notes,
                    'node' => [
                        'id' => $allocation->node->id,
                        'name' => $allocation->node->name,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function portHistory(Request $request, Server $server): JsonResponse
    {
        $request->validate(['port' => 'required|integer']);

        try {
            return response()->json([
                'port' => $request->input('port'),
                'history' => [],
                'message' => 'Port history tracking not yet implemented',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
