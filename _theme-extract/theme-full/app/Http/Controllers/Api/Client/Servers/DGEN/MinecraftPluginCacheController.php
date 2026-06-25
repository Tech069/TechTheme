<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftPluginCacheController extends Controller
{
    public function getCachedPlugins(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "plugins_cache_{$server->id}";
            $cached = Cache::get($cacheKey, []);

            return response()->json(['plugins' => $cached, 'cached_at' => Cache::get("{$cacheKey}_at")]);
        } catch (\Exception $e) {
            return response()->json(['plugins' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function cachePluginData(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory . '/plugins';
            $plugins = [];

            if (is_dir($path)) {
                foreach (glob("$path/*.jar") as $jar) {
                    $name = basename($jar, '.jar');
                    $plugins[] = [
                        'name' => $name,
                        'file' => basename($jar),
                        'size' => filesize($jar),
                        'modified' => date('Y-m-d H:i:s', filemtime($jar)),
                    ];
                }
            }

            $cacheKey = "plugins_cache_{$server->id}";
            Cache::put($cacheKey, $plugins, 3600);
            Cache::put("{$cacheKey}_at", now()->toDateTimeString(), 3600);

            return response()->json(['success' => true, 'count' => count($plugins), 'plugins' => $plugins]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cache plugin data: ' . $e->getMessage()], 500);
        }
    }

    public function clearCache(Request $request, Server $server): JsonResponse
    {
        try {
            Cache::forget("plugins_cache_{$server->id}");
            Cache::forget("plugins_cache_{$server->id}_at");

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to clear cache: ' . $e->getMessage()], 500);
        }
    }

    public function getCacheStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "plugins_cache_{$server->id}";
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
            $path = $server->server_data_directory . '/plugins';
            $versions = [];

            if (is_dir($path)) {
                foreach (glob("$path/*.jar") as $jar) {
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
