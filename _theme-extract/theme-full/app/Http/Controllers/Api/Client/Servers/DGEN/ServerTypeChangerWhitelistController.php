<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\DGEN\ServerSplitterWhitelist;

class ServerTypeChangerWhitelistController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $whitelist = ServerSplitterWhitelist::where('server_id', $server->id)->get();
            return response()->json(['whitelist' => $whitelist]);
        } catch (\Exception $e) {
            return response()->json(['whitelist' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'max_children' => 'required|integer|min:1|max:100',
        ]);

        try {
            $existing = ServerSplitterWhitelist::where('server_id', $server->id)->first();

            if ($existing) {
                $existing->update([
                    'max_children' => $request->input('max_children'),
                    'is_active' => true,
                ]);
                return response()->json(['success' => true, 'whitelist' => $existing]);
            }

            $whitelist = ServerSplitterWhitelist::create([
                'server_id' => $server->id,
                'max_children' => $request->input('max_children'),
                'is_active' => true,
            ]);

            return response()->json(['success' => true, 'whitelist' => $whitelist]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add to whitelist: ' . $e->getMessage()], 500);
        }
    }

    public function searchServers(Request $request, Server $server): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:1']);

        try {
            $query = $request->input('query');
            $servers = Server::where('name', 'LIKE', "%$query%")
                ->where('id', '!=', $server->id)
                ->limit(10)
                ->get(['id', 'name', 'node_id']);

            return response()->json(['servers' => $servers]);
        } catch (\Exception $e) {
            return response()->json(['servers' => []], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        try {
            $whitelist = ServerSplitterWhitelist::where('server_id', $server->id)->firstOrFail();
            $whitelist->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
