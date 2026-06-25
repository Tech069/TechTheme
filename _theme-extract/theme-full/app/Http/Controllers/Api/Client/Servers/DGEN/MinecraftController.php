<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Wings\DaemonFileController;

class MinecraftController extends Controller
{
    public function __construct(
        private DaemonFileController $fileController
    ) {}

    public function getPlayerCount(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['player_count' => 0, 'max_players' => $server->egg->config_import->get('config.default.max_players', 20)]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch player count'], 500);
        }
    }

    public function getConfiguration(Request $request, Server $server): JsonResponse
    {
        try {
            $config = $this->readConfigFile($server, 'server.properties');
            return response()->json(['configuration' => $config]);
        } catch (\Exception $e) {
            return response()->json(['configuration' => []]);
        }
    }

    public function getIcon(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory . '/server-icon.png';
            return response()->json(['icon' => null]);
        } catch (\Exception $e) {
            return response()->json(['icon' => null]);
        }
    }

    public function uploadIcon(Request $request, Server $server): JsonResponse
    {
        $request->validate(['icon' => 'required|image|mimes:png|max:64']);
        try {
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to upload icon'], 500);
        }
    }

    public function deleteIcon(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete icon'], 500);
        }
    }

    public function getMotd(Request $request, Server $server): JsonResponse
    {
        try {
            $config = $this->readConfigFile($server, 'server.properties');
            return response()->json(['motd' => $config['motd'] ?? 'A Minecraft Server']);
        } catch (\Exception $e) {
            return response()->json(['motd' => 'A Minecraft Server']);
        }
    }

    public function updateMotd(Request $request, Server $server): JsonResponse
    {
        $request->validate(['motd' => 'required|string|max:59']);
        try {
            $this->writeConfigLine($server, 'server.properties', 'motd', $request->input('motd'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update MOTD'], 500);
        }
    }

    public function getProperties(Request $request, Server $server): JsonResponse
    {
        try {
            $config = $this->readConfigFile($server, 'server.properties');
            return response()->json(['properties' => $config]);
        } catch (\Exception $e) {
            return response()->json(['properties' => []]);
        }
    }

    public function updateProperties(Request $request, Server $server): JsonResponse
    {
        $request->validate(['properties' => 'required|array']);
        try {
            foreach ($request->input('properties') as $key => $value) {
                $this->writeConfigLine($server, 'server.properties', $key, $value);
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update properties'], 500);
        }
    }

    public function getConfig(Request $request, Server $server): JsonResponse
    {
        try {
            $files = ['server.properties', 'bukkit.yml', 'spigot.yml', 'paper.yml', 'pufferfish.yml', 'purpur.yml'];
            $configs = [];
            foreach ($files as $file) {
                $configs[$file] = $this->readConfigFile($server, $file);
            }
            return response()->json(['configs' => $configs]);
        } catch (\Exception $e) {
            return response()->json(['configs' => []]);
        }
    }

    public function updateConfig(Request $request, Server $server): JsonResponse
    {
        $request->validate(['file' => 'required|string', 'key' => 'required|string', 'value' => 'required']);
        try {
            $this->writeConfigLine($server, $request->input('file'), $request->input('key'), $request->input('value'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update config'], 500);
        }
    }

    public function listYamlFiles(Request $request, Server $server): JsonResponse
    {
        try {
            $yamlFiles = ['bukkit.yml', 'spigot.yml', 'paper.yml', 'pufferfish.yml', 'purpur.yml', 'config/paper-global.yml'];
            return response()->json(['files' => $yamlFiles]);
        } catch (\Exception $e) {
            return response()->json(['files' => []]);
        }
    }

    public function getYamlFile(Request $request, Server $server): JsonResponse
    {
        $request->validate(['file' => 'required|string']);
        try {
            return response()->json(['content' => '', 'file' => $request->input('file')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to read YAML file'], 500);
        }
    }

    public function updateYamlFile(Request $request, Server $server): JsonResponse
    {
        $request->validate(['file' => 'required|string', 'content' => 'required|string']);
        try {
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update YAML file'], 500);
        }
    }

    public function debugDirectoryScan(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['directories' => []]);
        } catch (\Exception $e) {
            return response()->json(['directories' => []]);
        }
    }

    private function readConfigFile(Server $server, string $filename): array
    {
        $path = $server->server_data_directory . '/' . $filename;
        $config = [];
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        return $config;
    }

    private function writeConfigLine(Server $server, string $filename, string $key, $value): void
    {
        $path = $server->server_data_directory . '/' . $filename;
        if (!file_exists($path)) return;
        $content = file_get_contents($path);
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*.*/m';
        $content = preg_replace($pattern, $key . '=' . $value, $content);
        file_put_contents($path, $content);
    }
}
