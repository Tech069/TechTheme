<?php

namespace Pterodactyl\Http\Controllers\VexyThemes;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\VexyThemes\UpdateService;
use Illuminate\Support\Facades\File;

class UpdateController extends Controller
{
    public function __construct(
        private UpdateService $updateService,
    ) {}

    public function check(Request $request): JsonResponse
    {
        try {
            $licenseKey = $this->getLicenseKey();
            if (!$licenseKey) {
                return response()->json(['error' => 'No license key configured.'], 400);
            }

            $result = $this->updateService->checkForUpdate($licenseKey);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to check for updates.'], 500);
        }
    }

    public function currentVersion(): JsonResponse
    {
        $version = $this->updateService->getCurrentVersionInfo();
        return response()->json(['version' => $version]);
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $licenseKey = $this->getLicenseKey();
            if (!$licenseKey) {
                return response()->json(['error' => 'No license key configured.'], 400);
            }

            $version = $request->input('version');
            if (!$version) {
                return response()->json(['error' => 'Version required.'], 400);
            }

            $download = $this->updateService->downloadUpdate($licenseKey, $version);
            if (!$download['success']) {
                return response()->json($download, 400);
            }

            $zipPath = $download['zip_path'] ?? null;
            if (!$zipPath || !File::exists($zipPath)) {
                return response()->json(['error' => 'Update file not found.'], 400);
            }

            $result = $this->updateService->applyUpdate($zipPath);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function restore(Request $request): JsonResponse
    {
        try {
            $backupPath = $request->input('backup_path');
            if (!$backupPath) {
                return response()->json(['error' => 'Backup path required.'], 400);
            }

            $result = $this->updateService->restoreBackup($backupPath);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Restore failed: ' . $e->getMessage()], 500);
        }
    }

    public function backups(): JsonResponse
    {
        $backups = $this->updateService->getBackups();
        return response()->json(['backups' => $backups]);
    }

    private function getLicenseKey(): ?string
    {
        $setting = \Pterodactyl\Models\Setting::where('key', 'vexythemes:license_key')->first();
        return $setting?->value;
    }
}
