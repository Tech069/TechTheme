<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftModpackCacheController extends Controller
{
    public function getCachedModpacks(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "modpacks_cache_{$server->id}";
            $cached = Cache::get($cacheKey, []);

            return response()->json(['modpacks' => $cached, 'cached_at' => Cache::get("{$cacheKey}_at")]);
        } catch (\Exception $e) {
            return response()->json(['modpacks' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function getGameVersions(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $versions = [];

            $manifests = glob("$path/modpack-manifest*.json");
            foreach ($manifests as $manifest) {
                $data = json_decode(file_get_contents($manifest), true);
                if ($data) {
                    $versions[] = [
                        'name' => $data['name'] ?? basename($manifest),
                        'version' => $data['version'] ?? 'unknown',
                    ];
                }
            }

            return response()->json(['versions' => $versions]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }

    public function cacheModpackData(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $modpacks = [];

            $manifests = glob("$path/modpack-manifest*.json");
            foreach ($manifests as $manifest) {
                $data = json_decode(file_get_contents($manifest), true);
                if ($data) {
                    $modpacks[] = [
                        'name' => $data['name'] ?? basename($manifest, '.json'),
                        'version' => $data['version'] ?? 'unknown',
                        'author' => $data['author'] ?? 'unknown',
                        'manifest' => basename($manifest),
                    ];
                }
            }

            $cacheKey = "modpacks_cache_{$server->id}";
            Cache::put($cacheKey, $modpacks, 3600);
            Cache::put("{$cacheKey}_at", now()->toDateTimeString(), 3600);

            return response()->json(['success' => true, 'count' => count($modpacks), 'modpacks' => $modpacks]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cache modpack data: ' . $e->getMessage()], 500);
        }
    }

    public function getModpackVersions(Request $request, Server $server): JsonResponse
    {
        return $this->getGameVersions($request, $server);
    }

    public function clearCache(Request $request, Server $server): JsonResponse
    {
        try {
            Cache::forget("modpacks_cache_{$server->id}");
            Cache::forget("modpacks_cache_{$server->id}_at");

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to clear cache: ' . $e->getMessage()], 500);
        }
    }

    public function getCacheStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $cacheKey = "modpacks_cache_{$server->id}";
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
