<?php

namespace Pterodactyl\Services\Eggs\Sharing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RemoteEggService
{
    private const CACHE_PREFIX = 'remote_egg:';

    private const CACHE_TTL = 3600;

    private const DEFAULT_REPOSITORIES = [
        'https://raw.githubusercontent.com/parkervcp/eggs/master',
        'https://raw.githubusercontent.com/pterodactyl/eggs/master',
    ];

    public function __construct()
    {
    }

    /**
     * Fetch an egg configuration from a remote source.
     */
    public function fetchEgg(string $url): ?array
    {
        $cacheKey = self::CACHE_PREFIX . md5($url);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($url) {
            try {
                $response = Http::timeout(15)->get($url);

                if (!$response->successful()) {
                    Log::warning('Failed to fetch remote egg', [
                        'url' => $url,
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $data = $response->json();

                if (!$data) {
                    return null;
                }

                // Validate basic egg structure
                if (!isset($data['name']) || !isset($data['nest'])) {
                    Log::warning('Invalid egg structure from remote source', [
                        'url' => $url,
                    ]);

                    return null;
                }

                return $data;
            } catch (\Exception $exception) {
                Log::error('Error fetching remote egg', [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Fetch eggs from a repository listing.
     */
    public function fetchFromRepository(string $repositoryUrl): array
    {
        $eggs = [];

        try {
            $response = Http::timeout(15)->get($repositoryUrl . '/index.json');

            if ($response->successful()) {
                $listing = $response->json();

                if (is_array($listing)) {
                    foreach ($listing as $eggRef) {
                        if (isset($eggRef['url'])) {
                            $egg = $this->fetchEgg($eggRef['url']);
                            if ($egg) {
                                $eggs[] = $egg;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::error('Error fetching repository listing', [
                'url' => $repositoryUrl,
                'error' => $exception->getMessage(),
            ]);
        }

        return $eggs;
    }

    /**
     * Fetch eggs from all configured repositories.
     */
    public function fetchAllRepositories(): array
    {
        $allEggs = [];
        $repositories = config('eggs.remote_repositories', self::DEFAULT_REPOSITORIES);

        foreach ($repositories as $repo) {
            $eggs = $this->fetchFromRepository($repo);
            $allEggs = array_merge($allEggs, $eggs);
        }

        return $allEggs;
    }

    /**
     * Get available egg categories from a remote source.
     */
    public function getCategories(string $url): array
    {
        try {
            $response = Http::timeout(10)->get($url . '/categories.json');

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * Search for eggs by keyword across remote sources.
     */
    public function searchEggs(string $query): array
    {
        $results = [];
        $repositories = config('eggs.remote_repositories', self::DEFAULT_REPOSITORIES);

        foreach ($repositories as $repo) {
            try {
                $response = Http::timeout(15)->get($repo . '/search.json', ['q' => $query]);

                if ($response->successful()) {
                    $eggs = $response->json() ?? [];
                    $results = array_merge($results, $eggs);
                }
            } catch (\Exception $exception) {
                continue;
            }
        }

        return $results;
    }

    /**
     * Validate a remote egg before importing.
     */
    public function validateEgg(array $eggData): array
    {
        $errors = [];
        $warnings = [];

        // Required fields
        $requiredFields = ['name', 'description', 'author', 'nest', 'docker_images', 'startup'];
        foreach ($requiredFields as $field) {
            if (empty($eggData[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }

        // Validate docker images
        if (!empty($eggData['docker_images']) && !is_array($eggData['docker_images'])) {
            $errors[] = 'docker_images must be an array';
        }

        // Validate startup command
        if (!empty($eggData['startup']) && strlen($eggData['startup']) > 4096) {
            $warnings[] = 'Startup command is very long';
        }

        // Validate config files if present
        if (isset($eggData['config_files']) && !is_string($eggData['config_files'])) {
            $warnings[] = 'config_files should be a string';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
