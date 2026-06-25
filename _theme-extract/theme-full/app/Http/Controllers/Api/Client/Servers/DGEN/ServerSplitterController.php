<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSplit;

class ServerSplitterController extends Controller
{
    public function availableResources(Request $request, Server $server): JsonResponse
    {
        try {
            $node = $server->node;
            $usedMemory = $node->servers()->sum('memory');
            $usedDisk = $node->servers()->sum('disk');
            $usedCpu = $node->servers()->sum('cpu');

            return response()->json([
                'node' => [
                    'id' => $node->id,
                    'name' => $node->name,
                    'memory' => $node->memory,
                    'disk' => $node->disk,
                    'memory_used' => $usedMemory,
                    'disk_used' => $usedDisk,
                    'memory_available' => $node->memory - $usedMemory,
                    'disk_available' => $node->disk - $usedDisk,
                ],
                'server' => [
                    'memory' => $server->memory,
                    'disk' => $server->disk,
                    'cpu' => $server->cpu,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get resources: ' . $e->getMessage()], 500);
        }
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $splits = ServerSplit::where('parent_server_id', $server->id)
                ->orWhere('child_server_id', $server->id)
                ->with(['parentServer', 'childServer'])
                ->get();

            return response()->json(['splits' => $splits]);
        } catch (\Exception $e) {
            return response()->json(['splits' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'memory' => 'required|integer|min:128|max:' . $server->memory,
            'disk' => 'required|integer|min:100|max:' . $server->disk,
            'cpu' => 'required|integer|min:1|max:' . $server->cpu,
            'split_type' => 'required|string|max:191',
        ]);

        try {
            $split = ServerSplit::create([
                'parent_server_id' => $server->id,
                'child_server_id' => $server->id,
                'split_type' => $request->input('split_type'),
                'status' => 'active',
                'config' => [
                    'name' => $request->input('name'),
                    'memory' => $request->input('memory'),
                    'disk' => $request->input('disk'),
                    'cpu' => $request->input('cpu'),
                ],
            ]);

            return response()->json(['success' => true, 'split' => $split]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create split: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $request->validate(['split_id' => 'required|integer']);

        try {
            $split = ServerSplit::where('parent_server_id', $server->id)
                ->orWhere('child_server_id', $server->id)
                ->with(['parentServer', 'childServer'])
                ->findOrFail($request->input('split_id'));

            return response()->json(['split' => $split]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'split_id' => 'required|integer',
            'memory' => 'nullable|integer|min:128',
            'disk' => 'nullable|integer|min:100',
            'cpu' => 'nullable|integer|min:1',
        ]);

        try {
            $split = ServerSplit::where('parent_server_id', $server->id)
                ->orWhere('child_server_id', $server->id)
                ->findOrFail($request->input('split_id'));

            $config = $split->config ?? [];
            if ($request->has('memory')) $config['memory'] = $request->input('memory');
            if ($request->has('disk')) $config['disk'] = $request->input('disk');
            if ($request->has('cpu')) $config['cpu'] = $request->input('cpu');

            $split->update(['config' => $config]);

            return response()->json(['success' => true, 'split' => $split]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update split: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['split_id' => 'required|integer']);

        try {
            $split = ServerSplit::where('parent_server_id', $server->id)
                ->orWhere('child_server_id', $server->id)
                ->findOrFail($request->input('split_id'));

            $split->update(['status' => 'removed']);
            $split->delete();

            return response()->json(['success' => true, 'message' => 'Split removed']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete split: ' . $e->getMessage()], 500);
        }
    }
}
