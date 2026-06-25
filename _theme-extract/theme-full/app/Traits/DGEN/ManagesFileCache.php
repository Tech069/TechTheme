<?php

namespace Pterodactyl\Traits\DGEN;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

trait ManagesFileCache
{
    /**
     * The cache prefix for file operations.
     */
    protected string $fileCachePrefix = 'dgen:file:';

    /**
     * Get a cached file listing for a directory.
     */
    protected function getCachedFileListing(string $directory, int $ttl = 300): array
    {
        $cacheKey = $this->fileCachePrefix . md5($directory);

        return Cache::remember($cacheKey, $ttl, function () use ($directory) {
            return $this->scanDirectory($directory);
        });
    }

    /**
     * Scan a directory and return file information.
     */
    protected function scanDirectory(string $directory): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $files[] = [
                'path' => $file->getPathname(),
                'relative_path' => str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname()),
                'name' => $file->getFilename(),
                'is_directory' => $file->isDir(),
                'size' => $file->isFile() ? $file->getSize() : 0,
                'modified' => $file->getMTime(),
                'extension' => $file->isFile() ? $file->getExtension() : null,
            ];
        }

        return $files;
    }

    /**
     * Invalidate the cache for a directory.
     */
    protected function invalidateFileCache(string $directory): void
    {
        $cacheKey = $this->fileCachePrefix . md5($directory);
        Cache::forget($cacheKey);

        $parentDir = dirname($directory);
        if ($parentDir !== $directory) {
            $parentCacheKey = $this->fileCachePrefix . md5($parentDir);
            Cache::forget($parentCacheKey);
        }
    }

    /**
     * Get the size of a cached directory.
     */
    protected function getCachedDirectorySize(string $directory, int $ttl = 600): int
    {
        $cacheKey = $this->fileCachePrefix . 'size:' . md5($directory);

        return Cache::remember($cacheKey, $ttl, function () use ($directory) {
            return $this->calculateDirectorySize($directory);
        });
    }

    /**
     * Calculate directory size recursively.
     */
    protected function calculateDirectorySize(string $directory): int
    {
        $totalSize = 0;

        if (!is_dir($directory)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }

    /**
     * Check if a file exists in the cache.
     */
    protected function isFileCached(string $filePath): bool
    {
        $directory = dirname($filePath);
        $cacheKey = $this->fileCachePrefix . md5($directory);

        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return false;
        }

        foreach ($cached as $file) {
            if ($file['path'] === $filePath) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file metadata from cache or disk.
     */
    protected function getCachedFileMetadata(string $filePath, int $ttl = 300): ?array
    {
        $cacheKey = $this->fileCachePrefix . 'meta:' . md5($filePath);

        return Cache::remember($cacheKey, $ttl, function () use ($filePath) {
            if (!file_exists($filePath)) {
                return null;
            }

            return [
                'path' => $filePath,
                'name' => basename($filePath),
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                'is_readable' => is_readable($filePath),
                'is_writable' => is_writable($filePath),
            ];
        });
    }
}
