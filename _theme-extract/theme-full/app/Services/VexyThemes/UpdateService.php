<?php

namespace Pterodactyl\Services\VexyThemes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class UpdateService
{
    private string $apiUrl = 'https://vt-panel-api.vercel.app';
    private string $versionFile;
    private string $themePath;

    public function __construct()
    {
        $this->versionFile = base_path('config/vexythemes-version.json');
        $this->themePath = resource_path('/themes/vexythemes');
    }

    public function getCurrentVersion(): ?string
    {
        if (!File::exists($this->versionFile)) {
            return null;
        }
        $data = json_decode(File::get($this->versionFile), true);
        return $data['version'] ?? null;
    }

    public function getCurrentVersionInfo(): ?array
    {
        if (!File::exists($this->versionFile)) {
            return null;
        }
        return json_decode(File::get($this->versionFile), true);
    }

    public function checkForUpdate(string $licenseKey): array
    {
        try {
            $response = Http::timeout(15)->post($this->apiUrl . '/api/index', [
                '_endpoint' => 'update',
                'action' => 'check',
                'key' => $licenseKey,
                'current_version' => $this->getCurrentVersion(),
            ]);

            if ($response->failed()) {
                return ['available' => false, 'error' => 'Failed to check for updates.'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('VexyThemes update check failed: ' . $e->getMessage());
            return ['available' => false, 'error' => 'Connection failed.'];
        }
    }

    public function downloadUpdate(string $licenseKey, string $version): array
    {
        try {
            $response = Http::timeout(120)->post($this->apiUrl . '/api/index', [
                '_endpoint' => 'update',
                'action' => 'download',
                'key' => $licenseKey,
                'version' => $version,
            ]);

            if ($response->failed()) {
                return ['success' => false, 'error' => 'Failed to download update.'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('VexyThemes update download failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Download failed.'];
        }
    }

    public function applyUpdate(string $zipPath): array
    {
        try {
            $backupPath = $this->createBackup();
            
            $tempPath = storage_path('app/vexythemes-update-temp');
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return ['success' => false, 'error' => 'Failed to open update archive.'];
            }
            $zip->extractTo($tempPath);
            $zip->close();

            $extractedDir = $this->findExtractedDir($tempPath);
            if (!$extractedDir) {
                File::deleteDirectory($tempPath);
                return ['success' => false, 'error' => 'Invalid update archive structure.'];
            }

            $this->copyUpdateFiles($extractedDir);

            $versionFile = $extractedDir . '/config/vexythemes-version.json';
            if (File::exists($versionFile)) {
                File::copy($versionFile, $this->versionFile);
            }

            $this->clearCaches();

            File::deleteDirectory($tempPath);

            return [
                'success' => true,
                'version' => $this->getCurrentVersion(),
                'backup' => $backupPath,
            ];
        } catch (\Exception $e) {
            Log::error('VexyThemes update apply failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Update failed: ' . $e->getMessage()];
        }
    }

    private function createBackup(): string
    {
        $backupDir = storage_path('app/vexythemes-backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $version = $this->getCurrentVersion() ?? 'unknown';
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = "$backupDir/backup_{$version}_{$timestamp}";
        
        File::makeDirectory($backupPath, 0755, true);

        if (File::exists($this->themePath)) {
            $this->copyDir($this->themePath, "$backupPath/theme");
        }

        $versionFile = base_path('config/vexythemes-version.json');
        if (File::exists($versionFile)) {
            File::copy($versionFile, "$backupPath/vexythemes-version.json");
        }

        return $backupPath;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!File::exists($dst)) {
            File::makeDirectory($dst, 0755, true);
        }
        $items = File::allFiles($src);
        foreach ($items as $item) {
            $relative = ltrim(str_replace($src, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            $destFile = $dst . DIRECTORY_SEPARATOR . $relative;
            $destDir = dirname($destFile);
            if (!File::exists($destDir)) {
                File::makeDirectory($destDir, 0755, true);
            }
            File::copy($item->getPathname(), $destFile);
        }
    }

    private function findExtractedDir(string $tempPath): ?string
    {
        $dirs = File::directories($tempPath);
        foreach ($dirs as $dir) {
            if (File::exists($dir . '/config') || File::exists($dir . '/app')) {
                return $dir;
            }
        }
        if (File::exists($tempPath . '/config') || File::exists($tempPath . '/app')) {
            return $tempPath;
        }
        return null;
    }

    private function copyUpdateFiles(string $sourceDir): void
    {
        $themePath = $this->themePath;
        
        $dirs = ['app', 'config', 'routes', 'resources', 'database'];
        foreach ($dirs as $dir) {
            $src = $sourceDir . '/' . $dir;
            $dst = $themePath . '/' . $dir;
            if (File::exists($src)) {
                if (!File::exists($dst)) {
                    File::makeDirectory($dst, 0755, true);
                }
                $this->copyDir($src, $dst);
            }
        }

        $rootFiles = File::files($sourceDir);
        foreach ($rootFiles as $file) {
            if ($file->getExtension() === 'php' || $file->getExtension() === 'json') {
                File::copy($file->getPathname(), $themePath . '/' . $file->getFilename());
            }
        }
    }

    private function clearCaches(): void
    {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('view:clear');
        \Artisan::call('event:clear');
    }

    public function restoreBackup(string $backupPath): array
    {
        try {
            if (!File::exists($backupPath)) {
                return ['success' => false, 'error' => 'Backup not found.'];
            }

            $themeBackup = $backupPath . '/theme';
            if (File::exists($themeBackup)) {
                if (File::exists($this->themePath)) {
                    File::deleteDirectory($this->themePath);
                }
                $this->copyDir($themeBackup, $this->themePath);
            }

            $versionBackup = $backupPath . '/vexythemes-version.json';
            if (File::exists($versionBackup)) {
                File::copy($versionBackup, $this->versionFile);
            }

            $this->clearCaches();

            return ['success' => true, 'version' => $this->getCurrentVersion()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    public function getBackups(): array
    {
        $backupDir = storage_path('app/vexythemes-backups');
        if (!File::exists($backupDir)) {
            return [];
        }

        $backups = [];
        $dirs = File::directories($backupDir);
        foreach ($dirs as $dir) {
            $name = basename($dir);
            $versionFile = $dir . '/vexythemes-version.json';
            $version = 'unknown';
            if (File::exists($versionFile)) {
                $data = json_decode(File::get($versionFile), true);
                $version = $data['version'] ?? 'unknown';
            }
            $backups[] = [
                'path' => $dir,
                'name' => $name,
                'version' => $version,
                'created' => File::lastModified($dir),
            ];
        }

        usort($backups, fn($a, $b) => $b['created'] - $a['created']);
        return $backups;
    }
}
