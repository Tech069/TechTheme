<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class StartupPresetsController extends Controller
{
    public function getPresets(Request $request, Server $server): JsonResponse
    {
        try {
            $presets = $this->getDefaultPresets($server);
            return response()->json(['presets' => $presets]);
        } catch (\Exception $e) {
            return response()->json(['presets' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function applyPreset(Request $request, Server $server): JsonResponse
    {
        $request->validate(['preset' => 'required|string']);

        try {
            $presets = $this->getDefaultPresets($server);
            $presetName = $request->input('preset');
            $preset = collect($presets)->firstWhere('name', $presetName);

            if (!$preset) {
                return response()->json(['error' => 'Preset not found'], 422);
            }

            if (isset($preset['startup'])) {
                $server->update(['startup' => $preset['startup']]);
            }

            if (isset($preset['config'])) {
                foreach ($preset['config'] as $key => $value) {
                    $this->writeConfigLine($server->server_data_directory, 'server.properties', $key, $value);
                }
            }

            return response()->json(['success' => true, 'message' => "Preset '$presetName' applied"]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to apply preset: ' . $e->getMessage()], 500);
        }
    }

    public function updateStartup(Request $request, Server $server): JsonResponse
    {
        $request->validate(['startup' => 'required|string|max:500']);

        try {
            $server->update(['startup' => $request->input('startup')]);
            return response()->json(['success' => true, 'startup' => $request->input('startup')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update startup: ' . $e->getMessage()], 500);
        }
    }

    private function getDefaultPresets(Server $server): array
    {
        return [
            [
                'name' => 'vanilla',
                'description' => 'Default vanilla Minecraft server',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'config' => ['gamemode' => 'survival', 'difficulty' => 'normal', 'max-players' => '20'],
            ],
            [
                'name' => 'paper_performance',
                'description' => 'Paper with performance optimizations',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -DPaper.IgnoreWorldGenErrors=true -jar {{SERVER_JARFILE}}',
                'config' => ['view-distance' => '6', 'simulation-distance' => '4'],
            ],
            [
                'name' => 'modded_heavy',
                'description' => 'For heavy modpacks with more RAM',
                'startup' => 'java -Xms512M -Xmx{{SERVER_MEMORY}}M -XX:+UseG1GC -jar {{SERVER_JARFILE}}',
                'config' => [],
            ],
            [
                'name' => 'purpur_optimized',
                'description' => 'Purpur with optimized settings',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -DPaper.IgnoreWorldGenErrors=true -jar {{SERVER_JARFILE}}',
                'config' => ['purpur.elytra-continue-in-creative' => 'true'],
            ],
        ];
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
}
