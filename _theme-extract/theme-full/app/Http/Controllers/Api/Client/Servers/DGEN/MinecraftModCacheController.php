<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftModCacheController extends Controller
{
    public function getCachedMods(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "mods_cache_{$server->id}";
            $cached = Cache::get($cacheKey, []);

            return response()->json(['mods' => $cached, 'cached_at' => Cache::get("{$cacheKey}_at")]);
        } catch (\Exception $e) {
            return response()->json(['mods' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function cacheModData(Request $request, Server $server): JsonResponse
    {
        try {
            $modsPath = $server->server_data_directory . '/mods';
            $mods = [];

            if (is_dir($modsPath)) {
                foreach (glob("$modsPath/*.jar") as $jar) {
                    $name = basename($jar, '.jar');
                    $mods[] = [
                        'name' => $name,
                        'file' => basename($jar),
                        'size' => filesize($jar),
                        'modified' => date('Y-m-d H:i:s', filemtime($jar)),
                    ];
                }
            }

            $cacheKey = "mods_cache_{$server->id}";
            Cache::put($cacheKey, $mods, 3600);
            Cache::put("{$cacheKey}_at", now()->toDateTimeString(), 3600);

            return response()->json(['success' => true, 'count' => count($mods), 'mods' => $mods]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cache mod data: ' . $e->getMessage()], 500);
        }
    }

    public function clearCache(Request $request, Server $server): JsonResponse
    {
        try {
            Cache::forget("mods_cache_{$server->id}");
            Cache::forget("mods_cache_{$server->id}_at");

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to clear cache: ' . $e->getMessage()], 500);
        }
    }

    public function getCacheStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "mods_cache_{$server->id}";
            $exists = Cache::has($cacheKey);
            $cachedAt = Cache::get("{$cacheKey}_at");

            return response()->json([
                'cached' => $exists,
                'cached_at' => $cachedAt,
                'count' => $exists ? count(Cache::get($cacheKey, [])) : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['cached' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getGameVersions(Request $request, Server $server): JsonResponse
    {
        try {
            $modsPath = $server->server_data_directory . '/mods';
            $versions = [];

            if (is_dir($modsPath)) {
                foreach (glob("$modsPath/*.jar") as $jar) {
                    $name = basename($jar, '.jar');
                    $versions[] = ['name' => $name, 'file' => basename($jar)];
                }
            }

            return response()->json(['versions' => $versions]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }
}
