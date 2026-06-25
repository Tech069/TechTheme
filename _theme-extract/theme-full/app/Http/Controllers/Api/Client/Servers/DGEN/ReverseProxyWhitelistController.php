<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ReverseProxy;
use Pterodactyl\Models\DGEN\ReverseProxyWhitelist;

class ReverseProxyWhitelistController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $proxies = ReverseProxy::where('server_id', $server->id)->get();
            return response()->json(['proxies' => $proxies]);
        } catch (\Exception $e) {
            return response()->json(['proxies' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:191',
            'target_port' => 'required|integer|min:1|max:65535',
            'ssl_enabled' => 'sometimes|boolean',
        ]);

        try {
            $whitelist = ReverseProxyWhitelist::where('domain', $request->input('domain'))->first();
            if ($whitelist && !$whitelist->is_active) {
                return response()->json(['error' => 'Domain is blacklisted'], 422);
            }

            $proxy = ReverseProxy::create([
                'server_id' => $server->id,
                'domain' => $request->input('domain'),
                'target_port' => $request->input('target_port'),
                'ssl_enabled' => $request->boolean('ssl_enabled', true),
            ]);

            return response()->json(['success' => true, 'proxy' => $proxy]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create proxy: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'proxy_id' => 'required|integer|exists:reverse_proxies,id',
            'target_port' => 'nullable|integer|min:1|max:65535',
            'ssl_enabled' => 'nullable|boolean',
        ]);

        try {
            $proxy = ReverseProxy::where('server_id', $server->id)->findOrFail($request->input('proxy_id'));

            $data = array_filter([
                'target_port' => $request->input('target_port'),
                'ssl_enabled' => $request->has('ssl_enabled') ? $request->boolean('ssl_enabled') : null,
            ], fn($v) => $v !== null);

            $proxy->update($data);

            return response()->json(['success' => true, 'proxy' => $proxy]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update proxy: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['proxy_id' => 'required|integer|exists:reverse_proxies,id']);

        try {
            $proxy = ReverseProxy::where('server_id', $server->id)->findOrFail($request->input('proxy_id'));
            $proxy->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
                ->get(['id', 'name']);

            return response()->json(['servers' => $servers]);
        } catch (\Exception $e) {
            return response()->json(['servers' => []], 500);
        }
    }
}
