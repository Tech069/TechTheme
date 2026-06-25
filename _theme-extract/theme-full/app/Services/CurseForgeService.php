<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurseForgeService
{
    private const BASE_URL = 'https://api.curseforge.com/v1';

    private const CACHE_TTL = 1800;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.curseforge.api_key', '');
    }

    /**
     * Make an authenticated request to the CurseForge API.
     */
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('CurseForge API key is not configured.');

            return null;
        }

        try {
            $url = self::BASE_URL . $endpoint;

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(15);

            $response = match ($method) {
                'GET' => $response->get($url, $data),
                'POST' => $response->post($url, $data),
                default => $response->get($url, $data),
            };

            if ($response->successful()) {
                return $response->json('data');
            }

            Log::warning('CurseForge API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $exception) {
            Log::error('CurseForge API error', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for mods on CurseForge.
     */
    public function searchMods(string $query, int $gameId = 432, int $classId = 0, int $pageSize = 20, int $index = 0): array
    {
        $cacheKey = "curseforge:search:{$query}:{$gameId}:{$classId}:{$pageSize}:{$index}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $gameId, $classId, $pageSize, $index) {
            $data = $this->request('GET', '/mods/search', array_filter([
                'gameId' => $gameId,
                'classId' => $classId,
                'searchFilter' => $query,
                'pageSize' => $pageSize,
                'index' => $index,
            ]));

            return $data ?? ['data' => [], 'pagination' => ['totalCount' => 0]];
        });
    }

    /**
     * Get detailed information about a specific mod.
     */
    public function getModInfo(int $modId): ?array
    {
        $cacheKey = "curseforge:mod:{$modId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($modId) {
            return $this->request('GET', "/mods/$modId");
        });
    }

    /**
     * Get files for a specific mod.
     */
    public function getModFiles(int $modId, int $gameVersionTypeId = 0, int $pageSize = 20): array
    {
        $cacheKey = "curseforge:mod:{$modId}:files:{$gameVersionTypeId}:{$pageSize}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($modId, $gameVersionTypeId, $pageSize) {
            $data = $this->request('GET', "/mods/$modId/files", array_filter([
                'gameVersionTypeId' => $gameVersionTypeId,
                'pageSize' => $pageSize,
            ]));

            return $data ?? ['data' => [], 'pagination' => ['totalCount' => 0]];
        });
    }

    /**
     * Get a specific mod file download URL.
     */
    public function getModFileDownloadUrl(int $modId, int $fileId): ?string
    {
        $data = $this->request('GET', "/mods/$modId/files/$fileId/download-url");

        return $data['data'] ?? null;
    }

    /**
     * Search modpacks on CurseForge.
     */
    public function searchModpacks(string $query, int $pageSize = 20): array
    {
        return $this->searchMods($query, 432, 6, $pageSize);
    }
}
