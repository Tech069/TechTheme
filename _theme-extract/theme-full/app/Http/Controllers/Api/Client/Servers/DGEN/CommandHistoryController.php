<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\HyperCommandHistory;

class CommandHistoryController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $commands = HyperCommandHistory::where('server_id', $server->id)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json(['commands' => $commands]);
        } catch (\Exception $e) {
            return response()->json(['commands' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'command' => 'required|string|max:500',
            'output' => 'nullable|string|max:10000',
        ]);

        try {
            $history = HyperCommandHistory::create([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'command' => $request->input('command'),
                'output' => $request->input('output'),
            ]);

            return response()->json(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to log command: ' . $e->getMessage()], 500);
        }
    }
}
