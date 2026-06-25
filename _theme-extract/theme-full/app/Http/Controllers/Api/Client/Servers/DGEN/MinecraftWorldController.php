<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftWorldController extends Controller
{
    public function getInstalledWorlds(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $worlds = [];
            $serverProps = $this->getServerProperties($path);
            $levelName = $serverProps['level-name'] ?? 'world';

            $worldDirs = glob("$path/*", GLOB_ONLYDIR);
            foreach ($worldDirs as $dir) {
                $name = basename($dir);
                if (is_dir("$dir/region") || is_dir("$dir/DIM-1") || is_dir("$dir/DIM1")) {
                    $worlds[] = [
                        'name' => $name,
                        'is_active' => $name === $levelName,
                        'size' => $this->getDirectorySize($dir),
                        'modified' => date('Y-m-d H:i:s', filemtime($dir)),
                    ];
                }
            }

            return response()->json(['worlds' => $worlds, 'level_name' => $levelName]);
        } catch (\Exception $e) {
            return response()->json(['worlds' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function installWorld(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:mcpixels,worlddownload,custom',
            'world_id' => 'nullable|string',
            'name' => 'nullable|string|max:100',
        ]);

        try {
            $name = $request->input('name', 'new_world');
            $worldPath = $server->server_data_directory . '/' . $name;

            if (!is_dir($worldPath)) {
                mkdir($worldPath, 0755, true);
                mkdir("$worldPath/region", 0755, true);
            }

            return response()->json(['success' => true, 'message' => "World '$name' created", 'path' => $worldPath]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to install world: ' . $e->getMessage()], 500);
        }
    }

    public function uninstallWorld(Request $request, Server $server): JsonResponse
    {
        $request->validate(['world' => 'required|string']);

        try {
            $worldName = $request->input('world');
            $path = $server->server_data_directory;
            $serverProps = $this->getServerProperties($path);
            $activeWorld = $serverProps['level-name'] ?? 'world';

            if ($worldName === $activeWorld) {
                return response()->json(['error' => 'Cannot delete the active world'], 422);
            }

            $worldPath = "$path/$worldName";
            if (is_dir($worldPath)) {
                $this->deleteDirectory($worldPath);
                return response()->json(['success' => true, 'message' => "World '$worldName' deleted"]);
            }

            return response()->json(['error' => 'World not found'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to uninstall world: ' . $e->getMessage()], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }

    public function inspectServer(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $serverProps = $this->getServerProperties($path);
            $levelName = $serverProps['level-name'] ?? 'world';
            $worldPath = "$path/$levelName";

            $info = [
                'level_name' => $levelName,
                'world_exists' => is_dir($worldPath),
                'has_region' => is_dir("$worldPath/region"),
                'has_nether' => is_dir("$worldPath/DIM-1"),
                'has_end' => is_dir("$worldPath/DIM1"),
            ];

            if (is_dir($worldPath)) {
                $info['size'] = $this->getDirectorySize($worldPath);
                $info['region_files'] = count(glob("$worldPath/region/*.mca"));
            }

            return response()->json($info);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLevelName(Request $request, Server $server): JsonResponse
    {
        try {
            $serverProps = $this->getServerProperties($server->server_data_directory);
            return response()->json(['level_name' => $serverProps['level-name'] ?? 'world']);
        } catch (\Exception $e) {
            return response()->json(['level_name' => 'world'], 500);
        }
    }

    public function updateLevelName(Request $request, Server $server): JsonResponse
    {
        $request->validate(['level_name' => 'required|string|max:100|regex:/^[a-zA-Z0-9_\-.]+$/']);

        try {
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'level-name', $request->input('level_name'));
            return response()->json(['success' => true, 'level_name' => $request->input('level_name')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update level name: ' . $e->getMessage()], 500);
        }
    }

    public function getWorldVersions(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $worlds = glob("$path/*/region", GLOB_ONLYDIR);
            $versions = [];

            foreach ($worlds as $regionDir) {
                $worldName = basename(dirname($regionDir));
                $versions[] = ['name' => $worldName];
            }

            return response()->json(['versions' => $versions]);
        } catch (\Exception $e) {
            return response()->json(['versions' => []], 500);
        }
    }

    public function getWorldIcon(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $icons = glob("$path/icon*.{png,jpg,jpeg}", GLOB_BRACE);
            return response()->json(['icon' => !empty($icons) ? $icons[0] : null]);
        } catch (\Exception $e) {
            return response()->json(['icon' => null], 500);
        }
    }

    public function checkAddonAvailability(Request $request, Server $server): JsonResponse
    {
        return response()->json(['available' => true]);
    }

    private function getServerProperties(string $path): array
    {
        $file = "$path/server.properties";
        $config = [];
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        return $config;
    }

    private function writeConfigLine(string $path, string $filename, string $key, $value): void
    {
        $file = "$path/$filename";
        if (!file_exists($file)) return;
        $content = file_get_contents($file);
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*.*/m';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '=' . $value, $content);
        } else {
            $content .= "\n$key=$value";
        }
        file_put_contents($file, $content);
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
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
