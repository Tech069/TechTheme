<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HyperV2IntegrityService
{
    private const HASH_ALGO = 'sha256';

    private const INTEGRITY_CACHE_TTL = 3600;

    private const CRITICAL_PATHS = [
        'app/Services',
        'app/Models',
        'app/Http/Controllers',
        'routes',
        'config',
        'public/index.php',
    ];

    private const CHECKSUMS_FILE = 'storage/framework/integrity-checksums.json';

    public function __construct()
    {
    }

    /**
     * Generate a hash for a single file.
     */
    public function hashFile(string $filePath): ?string
    {
        if (!File::exists($filePath)) {
            return null;
        }

        return hash_file(self::HASH_ALGO, $filePath);
    }

    /**
     * Verify a single file against an expected hash.
     */
    public function verifyFile(string $filePath, string $expectedHash): array
    {
        $actualHash = $this->hashFile($filePath);

        return [
            'path' => $filePath,
            'expected' => $expectedHash,
            'actual' => $actualHash,
            'valid' => $actualHash === $expectedHash,
        ];
    }

    /**
     * Generate checksums for all files in given directories.
     */
    public function generateChecksums(array $paths = null): array
    {
        $paths = $paths ?? self::CRITICAL_PATHS;
        $checksums = [];
        $basePath = base_path();

        foreach ($paths as $relativePath) {
            $fullPath = $basePath . '/' . $relativePath;

            if (!File::exists($fullPath)) {
                continue;
            }

            if (File::isFile($fullPath)) {
                $checksums[$relativePath] = $this->hashFile($fullPath);
                continue;
            }

            $files = File::allFiles($fullPath);
            foreach ($files as $file) {
                $relative = $file->getRelativePathname();
                $fullRelativePath = $relativePath . '/' . $relative;
                $checksums[$fullRelativePath] = hash_file(self::HASH_ALGO, $file->getPathname());
            }
        }

        return $checksums;
    }

    /**
     * Save checksums to disk.
     */
    public function saveChecksums(array $checksums = null): bool
    {
        $checksums = $checksums ?? $this->generateChecksums();
        $path = storage_path('framework/integrity-checksums.json');
        $directory = dirname($path);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $json = json_encode($checksums, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to encode integrity checksums', [
                'error' => json_last_error_msg(),
            ]);

            return false;
        }

        File::put($path, $json);

        return true;
    }

    /**
     * Load saved checksums from disk.
     */
    public function loadChecksums(): array
    {
        $path = storage_path('framework/integrity-checksums.json');

        if (!File::exists($path)) {
            return [];
        }

        $contents = File::get($path);
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to decode integrity checksums', [
                'error' => json_last_error_msg(),
            ]);

            return [];
        }

        return $decoded;
    }

    /**
     * Run integrity check against saved checksums.
     */
    public function verifyIntegrity(): array
    {
        $savedChecksums = $this->loadChecksums();
        $currentChecksums = $this->generateChecksums(array_keys($savedChecksums));
        $basePath = base_path();

        $results = [
            'modified' => [],
            'missing' => [],
            'new' => [],
            'valid' => [],
        ];

        // Check for modified and missing files
        foreach ($savedChecksums as $relativePath => $expectedHash) {
            $fullPath = $basePath . '/' . $relativePath;

            if (!File::exists($fullPath)) {
                $results['missing'][] = [
                    'path' => $relativePath,
                    'expected_hash' => $expectedHash,
                ];
                continue;
            }

            $actualHash = $this->hashFile($fullPath);

            if ($actualHash === $expectedHash) {
                $results['valid'][] = $relativePath;
            } else {
                $results['modified'][] = [
                    'path' => $relativePath,
                    'expected_hash' => $expectedHash,
                    'actual_hash' => $actualHash,
                ];
            }
        }

        // Check for new files
        foreach ($currentChecksums as $relativePath => $hash) {
            if (!isset($savedChecksums[$relativePath])) {
                $results['new'][] = [
                    'path' => $relativePath,
                    'hash' => $hash,
                ];
            }
        }

        return $results;
    }

    /**
     * Quick integrity check returning only a boolean.
     */
    public function isIntact(): bool
    {
        $results = $this->verifyIntegrity();

        return empty($results['modified']) && empty($results['missing']);
    }

    /**
     * Get cached integrity status.
     */
    public function getCachedStatus(): array
    {
        return Cache::remember('integrity:status', self::INTEGRITY_CACHE_TTL, function () {
            return $this->verifyIntegrity();
        });
    }

    /**
     * Force refresh the integrity cache.
     */
    public function refreshStatus(): array
    {
        Cache::forget('integrity:status');

        return $this->getCachedStatus();
    }
}
