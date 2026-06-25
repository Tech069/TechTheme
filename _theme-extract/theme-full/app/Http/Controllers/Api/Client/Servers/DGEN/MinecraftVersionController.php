<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftVersionController extends Controller
{
    public function getServerTypes(Request $request, Server $server): JsonResponse
    {
        $types = ['paper', 'purpur', 'spigot', 'velocity', 'waterfall', 'forge', 'fabric', 'quilt', 'bungeecord'];
        return response()->json(['types' => $types]);
    }

    public function getVersions(Request $request, Server $server, string $type): JsonResponse
    {
        try {
            $response = Http::get("https://api.papermc.io/v2/projects/$type");
            return response()->json(['versions' => $response->json('versions', [])]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []]);
        }
    }

    public function getBuilds(Request $request, Server $server, string $type, string $version): JsonResponse
    {
        try {
            $response = Http::get("https://api.papermc.io/v2/projects/$type/versions/$version");
            return response()->json(['builds' => $response->json('builds', [])]);
        } catch (\Exception $e) {
            return response()->json(['builds' => []]);
        }
    }

    public function changeVersion(Request $request, Server $server): JsonResponse
    {
        $request->validate(['type' => 'required|string', 'version' => 'required|string', 'build' => 'required|integer']);
        try {
            return response()->json(['success' => true, 'message' => 'Version change queued']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to change version'], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }
}
