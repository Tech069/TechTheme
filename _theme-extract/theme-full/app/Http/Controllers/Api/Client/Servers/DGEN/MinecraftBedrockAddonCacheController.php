<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftBedrockAddonCacheController extends Controller
{
    public function getCachedAddons(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "bedrock_addons_cache_{$server->id}";
            $cached = Cache::get($cacheKey, []);

            return response()->json(['addons' => $cached, 'cached_at' => Cache::get("{$cacheKey}_at")]);
        } catch (\Exception $e) {
            return response()->json(['addons' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function cacheAddonData(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $addons = [];

            foreach (['behavior_packs', 'resource_packs'] as $type) {
                $packs = glob("$path/$type/*", GLOB_ONLYDIR);
                foreach ($packs as $pack) {
                    $manifestPath = "$pack/manifest.json";
                    if (file_exists($manifestPath)) {
                        $manifest = json_decode(file_get_contents($manifestPath), true);
                        $addons[] = [
                            'name' => $manifest['header']['name'] ?? basename($pack),
                            'uuid' => $manifest['header']['uuid'] ?? '',
                            'version' => implode('.', $manifest['header']['version'] ?? [0, 0, 0]),
                            'type' => $type === 'behavior_packs' ? 'behavior' : 'resource',
                            'path' => basename($pack),
                        ];
                    }
                }
            }

            $cacheKey = "bedrock_addons_cache_{$server->id}";
            Cache::put($cacheKey, $addons, 3600);
            Cache::put("{$cacheKey}_at", now()->toDateTimeString(), 3600);

            return response()->json(['success' => true, 'count' => count($addons), 'addons' => $addons]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cache addon data: ' . $e->getMessage()], 500);
        }
    }

    public function clearCache(Request $request, Server $server): JsonResponse
    {
        try {
            Cache::forget("bedrock_addons_cache_{$server->id}");
            Cache::forget("bedrock_addons_cache_{$server->id}_at");

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to clear cache: ' . $e->getMessage()], 500);
        }
    }

    public function getCacheStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "bedrock_addons_cache_{$server->id}";
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
}
