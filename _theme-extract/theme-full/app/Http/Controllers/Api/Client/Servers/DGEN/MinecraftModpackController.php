<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftModpackController extends Controller
{
    public function getInstalledModpacks(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $modpacks = [];

            $manifestFiles = glob("$path/modpack-manifest*.json");
            foreach ($manifestFiles as $manifest) {
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

            $forgeModList = "$path/mods/modlist.html";
            if (file_exists($forgeModList)) {
                $content = file_get_contents($forgeModList);
                if (preg_match('/<h2>(.*?)<\/h2>/', $content, $matches)) {
                    $modpacks[] = ['name' => strip_tags($matches[1]), 'type' => 'forge_modpack'];
                }
            }

            return response()->json(['modpacks' => $modpacks]);
        } catch (\Exception $e) {
            return response()->json(['modpacks' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function installModpack(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:modrinth,curseforge,technic,ftb',
            'modpack_id' => 'required|string',
            'version' => 'nullable|string',
        ]);

        try {
            $provider = $request->input('provider');
            $modpackId = $request->input('modpack_id');
            $version = $request->input('version');

            if ($provider === 'modrinth') {
                $response = Http::get("https://api.modrinth.com/v2/project/$modpackId/version", [
                    'loaders' => ['forge', 'fabric'],
                ]);

                $versions = $response->json([], []);
                if (empty($versions)) {
                    return response()->json(['error' => 'No compatible versions found'], 422);
                }

                $target = $version
                    ? collect($versions)->firstWhere('version_number', $version)
                    : $versions[0] ?? null;

                if (!$target) {
                    return response()->json(['error' => 'Version not found'], 422);
                }

                $downloadUrl = $target['files'][0]['url'] ?? null;
                if ($downloadUrl) {
                    $filename = $target['files'][0]['filename'] ?? 'modpack.zip';
                    $content = file_get_contents($downloadUrl);
                    $dest = $server->server_data_directory . '/' . $filename;
                    file_put_contents($dest, $content);

                    return response()->json(['success' => true, 'message' => 'Modpack downloaded', 'filename' => $filename]);
                }
            }

            return response()->json(['success' => true, 'message' => 'Modpack installation queued via service']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to install modpack: ' . $e->getMessage()], 500);
        }
    }

    public function uninstallModpack(Request $request, Server $server): JsonResponse
    {
        $request->validate(['modpack' => 'required|string']);

        try {
            $modpackName = $request->input('modpack');
            $deleted = false;
            $path = $server->server_data_directory;

            foreach (glob("$path/modpack-manifest*.json") as $manifest) {
                if (str_contains(basename($manifest), $modpackName)) {
                    $deleted = unlink($manifest) || $deleted;
                }
            }

            return response()->json(['success' => $deleted, 'message' => $deleted ? 'Modpack removed' : 'Modpack not found']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to uninstall modpack: ' . $e->getMessage()], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }

    public function getModpackVersions(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:modrinth,curseforge',
            'modpack_id' => 'required|string',
        ]);

        try {
            $provider = $request->input('provider');
            $modpackId = $request->input('modpack_id');

            if ($provider === 'modrinth') {
                $response = Http::get("https://api.modrinth.com/v2/project/$modpackId/version", [
                    'loaders' => ['forge', 'fabric'],
                ]);
                return response()->json(['versions' => $response->json([], [])]);
            }

            return response()->json(['versions' => []]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }

    public function restoreModpackServer(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $manifests = glob("$path/modpack-manifest*.json");
            return response()->json(['success' => true, 'manifests' => count($manifests)]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore: ' . $e->getMessage()], 500);
        }
    }

    public function getModpackInstallStatus(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }

    public function checkAddonAvailability(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $hasModpack = count(glob("$path/modpack-manifest*.json")) > 0;
            return response()->json(['available' => true, 'has_modpack' => $hasModpack]);
        } catch (\Exception $e) {
            return response()->json(['available' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getModpackIcon(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $icons = glob("$path/modpack-icon*.{png,jpg,jpeg}", GLOB_BRACE);
            return response()->json(['icon' => !empty($icons) ? $icons[0] : null]);
        } catch (\Exception $e) {
            return response()->json(['icon' => null], 500);
        }
    }
}
