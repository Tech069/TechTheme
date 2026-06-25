<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Theme;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Setting;

class HyperV2AddonController extends Controller
{
    public function show(Request $request, Server $server): JsonResponse
    {
        try {
            $addons = $this->getEnabledAddons($server->id);

            return response()->json([
                'addons' => $addons,
                'total_enabled' => count($addons),
            ]);
        } catch (\Exception $e) {
            return response()->json(['addons' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function defaults(Request $request, Server $server): JsonResponse
    {
        try {
            $defaults = $this->getDefaultAddonConfig();

            return response()->json(['defaults' => $defaults]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $request->validate(['addons' => 'required|array']);

        try {
            $this->saveEnabledAddons($server->id, $request->input('addons'));

            return response()->json(['success' => true, 'message' => 'Addon settings updated']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function exportRaw(Request $request, Server $server): JsonResponse
    {
        try {
            $config = [
                'server_id' => $server->id,
                'addons' => $this->getEnabledAddons($server->id),
                'exported_at' => now()->toDateTimeString(),
            ];

            return response()->json(['config' => $config]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkServerAvailability(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json([
                'available' => true,
                'server_id' => $server->id,
                'node_online' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json(['available' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function getEnabledAddons(int $serverId): array
    {
        $cacheKey = "addons_{$serverId}";
        return Cache::get($cacheKey, []);
    }

    private function saveEnabledAddons(int $serverId, array $addons): void
    {
        $cacheKey = "addons_{$serverId}";
        Cache::put($cacheKey, $addons, 86400);
    }

    private function getDefaultAddonConfig(): array
    {
        return [
            'server_stats' => ['enabled' => true, 'refresh_interval' => 5],
            'player_list' => ['enabled' => true, 'show_offline' => false],
            'file_manager' => ['enabled' => true, 'max_upload_size' => 100],
            'console' => ['enabled' => true, 'max_lines' => 100],
            'backup_manager' => ['enabled' => true, 'max_backups' => 5],
        ];
    }
}
