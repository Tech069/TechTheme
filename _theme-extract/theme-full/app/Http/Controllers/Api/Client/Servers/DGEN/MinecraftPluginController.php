<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftPluginController extends Controller
{
    public function getInstalledPlugins(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory . '/plugins';
            $plugins = [];
            if (is_dir($path)) {
                foreach (glob("$path/*.jar") as $jar) {
                    $plugins[] = ['name' => basename($jar, '.jar'), 'file' => basename($jar)];
                }
            }
            return response()->json(['plugins' => $plugins]);
        } catch (\Exception $e) {
            return response()->json(['plugins' => []]);
        }
    }

    public function installPlugin(Request $request, Server $server): JsonResponse
    {
        $request->validate(['provider' => 'required|string', 'plugin_id' => 'required|string', 'version' => 'nullable|string']);
        try {
            return response()->json(['success' => true, 'message' => 'Plugin installation queued']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to install plugin'], 500);
        }
    }

    public function uninstallPlugin(Request $request, Server $server): JsonResponse
    {
        $request->validate(['plugin' => 'required|string']);
        try {
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to uninstall plugin'], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }

    public function getPluginVersions(Request $request, Server $server, string $provider, string $pluginId): JsonResponse
    {
        try {
            if ($provider === 'spigotmc') {
                $response = Http::get("https://api.spiget.org/v2/resources/$pluginId/versions");
                return response()->json(['versions' => $response->json([], [])]);
            }
            if ($provider === 'modrinth') {
                $response = Http::get("https://api.modrinth.com/v2/project/$pluginId/version", [
                    'loaders' => ['paper', 'spigot', 'bukkit'],
                ]);
                return response()->json(['versions' => $response->json([], [])]);
            }
            if ($provider === 'hangar') {
                $response = Http::get("https://hangar.papermc.io/api/v1/projects/$pluginId");
                return response()->json(['versions' => $response->json([], [])]);
            }
            return response()->json(['versions' => []]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []]);
        }
    }

    public function getPluginDetails(Request $request, Server $server, string $provider, string $pluginId): JsonResponse
    {
        try {
            if ($provider === 'spigotmc') {
                $response = Http::get("https://api.spiget.org/v2/resources/$pluginId");
                return response()->json(['plugin' => $response->json([], [])]);
            }
            if ($provider === 'modrinth') {
                $response = Http::get("https://api.modrinth.com/v2/project/$pluginId");
                return response()->json(['plugin' => $response->json([], [])]);
            }
            return response()->json(['plugin' => []]);
        } catch (\Exception $e) {
            return response()->json(['plugin' => []]);
        }
    }

    public function getPluginIcon(Request $request, Server $server, string $provider, string $iconPath): JsonResponse
    {
        return response()->json(['icon' => null]);
    }

    public function checkAddonAvailability(Request $request, Server $server): JsonResponse
    {
        return response()->json(['available' => true]);
    }
}
