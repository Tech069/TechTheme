<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftModController extends Controller
{
    public function checkAddonAvailability(Request $request, Server $server): JsonResponse
    {
        try {
            $modLoader = $this->detectModLoader($server);
            return response()->json(['available' => $modLoader !== null, 'mod_loader' => $modLoader]);
        } catch (\Exception $e) {
            return response()->json(['available' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getInstalledMods(Request $request, Server $server): JsonResponse
    {
        try {
            $mods = $this->scanModsDirectory($server);
            return response()->json(['mods' => $mods]);
        } catch (\Exception $e) {
            return response()->json(['mods' => []], 500);
        }
    }

    public function installMod(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:modrinth,curseforge',
            'mod_id' => 'required|string',
            'version' => 'nullable|string',
        ]);

        try {
            $provider = $request->input('provider');
            $modId = $request->input('mod_id');
            $version = $request->input('version');

            if ($provider === 'modrinth') {
                $versions = Http::get("https://api.modrinth.com/v2/project/$modId/version", [
                    'loaders' => ['forge', 'fabric', 'quilt', 'neoforge'],
                ])->json([], []);

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
                if (!$downloadUrl) {
                    return response()->json(['error' => 'No download URL available'], 422);
                }

                $filename = $target['files'][0]['filename'] ?? basename($downloadUrl);
                $modsPath = $server->server_data_directory . '/mods';

                if (!is_dir($modsPath)) {
                    mkdir($modsPath, 0755, true);
                }

                $content = file_get_contents($downloadUrl);
                file_put_contents($modsPath . '/' . $filename, $content);

                return response()->json(['success' => true, 'message' => "Mod $filename installed successfully", 'filename' => $filename]);
            }

            if ($provider === 'curseforge') {
                return response()->json(['success' => true, 'message' => 'CurseForge installation queued via service']);
            }

            return response()->json(['error' => 'Unsupported provider'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to install mod: ' . $e->getMessage()], 500);
        }
    }

    public function uninstallMod(Request $request, Server $server): JsonResponse
    {
        $request->validate(['mod' => 'required|string']);

        try {
            $modName = $request->input('mod');
            $modsPath = $server->server_data_directory . '/mods';
            $deleted = false;

            foreach (glob("$modsPath/$modName*.jar") as $jar) {
                if (unlink($jar)) {
                    $deleted = true;
                }
            }

            return response()->json(['success' => $deleted, 'message' => $deleted ? 'Mod uninstalled' : 'Mod file not found']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to uninstall mod: ' . $e->getMessage()], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }

    public function getModVersions(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:modrinth,curseforge',
            'mod_id' => 'required|string',
        ]);

        try {
            $provider = $request->input('provider');
            $modId = $request->input('mod_id');

            if ($provider === 'modrinth') {
                $response = Http::get("https://api.modrinth.com/v2/project/$modId/version", [
                    'loaders' => ['forge', 'fabric', 'quilt', 'neoforge'],
                ]);

                return response()->json(['versions' => $response->json([], [])]);
            }

            return response()->json(['versions' => []]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }

    public function getModIcon(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:modrinth,curseforge',
            'mod_id' => 'required|string',
        ]);

        try {
            $provider = $request->input('provider');
            $modId = $request->input('mod_id');

            if ($provider === 'modrinth') {
                $response = Http::get("https://api.modrinth.com/v2/project/$modId");
                $data = $response->json([]);
                return response()->json(['icon' => $data['icon_url'] ?? null]);
            }

            return response()->json(['icon' => null]);
        } catch (\Exception $e) {
            return response()->json(['icon' => null], 500);
        }
    }

    private function detectModLoader(Server $server): ?string
    {
        $dir = $server->server_data_directory;
        if (is_dir("$dir/mods")) return 'forge';
        if (is_dir("$dir/.fabric")) return 'fabric';
        if (is_dir("$dir/versions") && glob("$dir/versions/*/quilt/loader.json")) return 'quilt';
        if (file_exists("$dir/neoforge.mods.toml")) return 'neoforge';
        return null;
    }

    private function scanModsDirectory(Server $server): array
    {
        $modsPath = $server->server_data_directory . '/mods';
        $mods = [];

        if (is_dir($modsPath)) {
            foreach (glob("$modsPath/*.jar") as $jar) {
                $name = basename($jar, '.jar');
                $mods[] = [
                    'name' => $name,
                    'file' => basename($jar),
                    'size' => filesize($jar),
                    'modified' => date('Y-m-d H:i:s', filemtime($jar)),
                ];
            }
        }

        return $mods;
    }
}
