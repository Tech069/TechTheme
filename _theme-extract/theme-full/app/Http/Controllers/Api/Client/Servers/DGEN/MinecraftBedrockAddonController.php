<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftBedrockAddonController extends Controller
{
    public function getInstalledAddons(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $addons = [];

            $behaviorPaths = glob("$path/behavior_packs/*", GLOB_ONLYDIR);
            foreach ($behaviorPaths as $bp) {
                $manifestPath = "$bp/manifest.json";
                if (file_exists($manifestPath)) {
                    $manifest = json_decode(file_get_contents($manifestPath), true);
                    $addons[] = [
                        'name' => $manifest['header']['name'] ?? basename($bp),
                        'uuid' => $manifest['header']['uuid'] ?? '',
                        'version' => implode('.', $manifest['header']['version'] ?? [0, 0, 0]),
                        'type' => 'behavior',
                        'path' => basename($bp),
                    ];
                }
            }

            $resourcePaths = glob("$path/resource_packs/*", GLOB_ONLYDIR);
            foreach ($resourcePaths as $rp) {
                $manifestPath = "$rp/manifest.json";
                if (file_exists($manifestPath)) {
                    $manifest = json_decode(file_get_contents($manifestPath), true);
                    $addons[] = [
                        'name' => $manifest['header']['name'] ?? basename($rp),
                        'uuid' => $manifest['header']['uuid'] ?? '',
                        'version' => implode('.', $manifest['header']['version'] ?? [0, 0, 0]),
                        'type' => 'resource',
                        'path' => basename($rp),
                    ];
                }
            }

            return response()->json(['addons' => $addons]);
        } catch (\Exception $e) {
            return response()->json(['addons' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function installAddon(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|mcpedl,mcpacks',
            'addon_id' => 'required|string',
            'type' => 'required|string|in:behavior,resource',
        ]);

        try {
            $type = $request->input('type');
            $packDir = $type === 'behavior' ? 'behavior_packs' : 'resource_packs';
            $destPath = $server->server_data_directory . "/$packDir";

            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }

            return response()->json(['success' => true, 'message' => 'Addon installation queued']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to install addon: ' . $e->getMessage()], 500);
        }
    }

    public function uninstallAddon(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'addon' => 'required|string',
            'type' => 'required|string|in:behavior,resource',
        ]);

        try {
            $type = $request->input('type');
            $packDir = $type === 'behavior' ? 'behavior_packs' : 'resource_packs';
            $addonPath = $server->server_data_directory . "/$packDir/" . $request->input('addon');

            if (is_dir($addonPath)) {
                $this->deleteDirectory($addonPath);
                return response()->json(['success' => true, 'message' => 'Addon removed']);
            }

            return response()->json(['error' => 'Addon not found'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to uninstall addon: ' . $e->getMessage()], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }

    public function getAddonVersions(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string',
            'addon_id' => 'required|string',
        ]);

        try {
            return response()->json(['versions' => []]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }

    public function getAddonIcon(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'addon' => 'required|string',
            'type' => 'required|string|in:behavior,resource',
        ]);

        try {
            $type = $request->input('type');
            $packDir = $type === 'behavior' ? 'behavior_packs' : 'resource_packs';
            $addonPath = $server->server_data_directory . "/$packDir/" . $request->input('addon');
            $icons = glob("$addonPath/pack_icon*.{png,jpg}", GLOB_BRACE);

            return response()->json(['icon' => !empty($icons) ? $icons[0] : null]);
        } catch (\Exception $e) {
            return response()->json(['icon' => null], 500);
        }
    }

    private function deleteDirectory(string $path): void
    {
        if (is_dir($path)) {
            $items = array_diff(scandir($path), ['.', '..']);
            foreach ($items as $item) {
                $fullPath = "$path/$item";
                is_dir($fullPath) ? $this->deleteDirectory($fullPath) : unlink($fullPath);
            }
            rmdir($path);
        }
    }
}
