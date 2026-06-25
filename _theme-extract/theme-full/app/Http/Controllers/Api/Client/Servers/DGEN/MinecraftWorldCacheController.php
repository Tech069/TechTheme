<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftWorldCacheController extends Controller
{
    public function getCachedWorlds(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "worlds_cache_{$server->id}";
            $cached = Cache::get($cacheKey, []);

            return response()->json(['worlds' => $cached, 'cached_at' => Cache::get("{$cacheKey}_at")]);
        } catch (\Exception $e) {
            return response()->json(['worlds' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function cacheWorldData(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $worlds = [];

            $worldDirs = glob("$path/*", GLOB_ONLYDIR);
            foreach ($worldDirs as $dir) {
                $name = basename($dir);
                if (is_dir("$dir/region")) {
                    $worlds[] = [
                        'name' => $name,
                        'region_files' => count(glob("$dir/region/*.mca")),
                        'has_nether' => is_dir("$dir/DIM-1"),
                        'has_end' => is_dir("$dir/DIM1"),
                    ];
                }
            }

            $cacheKey = "worlds_cache_{$server->id}";
            Cache::put($cacheKey, $worlds, 3600);
            Cache::put("{$cacheKey}_at", now()->toDateTimeString(), 3600);

            return response()->json(['success' => true, 'count' => count($worlds), 'worlds' => $worlds]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cache world data: ' . $e->getMessage()], 500);
        }
    }

    public function clearCache(Request $request, Server $server): JsonResponse
    {
        try {
            Cache::forget("worlds_cache_{$server->id}");
            Cache::forget("worlds_cache_{$server->id}_at");

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to clear cache: ' . $e->getMessage()], 500);
        }
    }

    public function getCacheStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "worlds_cache_{$server->id}";
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
            $path = $server->server_data_directory;
            $versions = [];
            $worldDirs = glob("$path/*/region", GLOB_ONLYDIR);

            foreach ($worldDirs as $regionDir) {
                $versions[] = ['name' => basename(dirname($regionDir))];
            }

            return response()->json(['versions' => $versions]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }
}
