<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSplit;
use Pterodactyl\Models\User;

class ServerSplitterMigrationController extends Controller
{
    public function getLegacySplits(Request $request, Server $server): JsonResponse
    {
        try {
            $legacySplits = ServerSplit::where('parent_server_id', $server->id)
                ->where('status', 'legacy')
                ->get();

            return response()->json(['legacy_splits' => $legacySplits]);
        } catch (\Exception $e) {
            return response()->json(['legacy_splits' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function migrateLegacySplit(Request $request, Server $server): JsonResponse
    {
        $request->validate(['split_id' => 'required|integer']);

        try {
            $split = ServerSplit::where('id', $request->input('split_id'))
                ->where('parent_server_id', $server->id)
                ->where('status', 'legacy')
                ->firstOrFail();

            $split->update(['status' => 'active']);

            return response()->json(['success' => true, 'message' => 'Legacy split migrated', 'split' => $split]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to migrate: ' . $e->getMessage()], 500);
        }
    }

    public function searchUsers(Request $request, Server $server): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:2']);

        try {
            $query = $request->input('query');
            $users = User::where('username', 'LIKE', "%$query%")
                ->orWhere('email', 'LIKE', "%$query%")
                ->limit(10)
                ->get(['id', 'username', 'email']);

            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            return response()->json(['users' => []], 500);
        }
    }

    public function getUserServers(Request $request, Server $server): JsonResponse
    {
        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        try {
            $user = User::findOrFail($request->input('user_id'));
            $servers = $user->servers()->get(['id', 'name', 'node_id', 'egg_id']);

            return response()->json(['servers' => $servers]);
        } catch (\Exception $e) {
            return response()->json(['servers' => []], 500);
        }
    }

    public function hookServer(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'target_server_id' => 'required|integer|exists:servers,id',
        ]);

        try {
            $targetServer = Server::findOrFail($request->input('target_server_id'));

            $split = ServerSplit::create([
                'parent_server_id' => $server->id,
                'child_server_id' => $targetServer->id,
                'split_type' => 'hook',
                'status' => 'active',
                'config' => [
                    'user_id' => $request->input('user_id'),
                    'hooked_at' => now()->toDateTimeString(),
                ],
            ]);

            return response()->json(['success' => true, 'split' => $split]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to hook server: ' . $e->getMessage()], 500);
        }
    }
}
