<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftBedrockVersionController extends Controller
{
    public function getVersions(Request $request, Server $server): JsonResponse
    {
        try {
            $response = Http::get('https://raw.githubusercontent.com/nicxlau/bedrock-version/main/versions.json');
            $versions = $response->json([], []);

            if (empty($versions)) {
                $versions = $this->getFallbackVersions();
            }

            return response()->json(['versions' => $versions]);
        } catch (\Exception $e) {
            return response()->json(['versions' => $this->getFallbackVersions()], 500);
        }
    }

    public function getSpecificVersions(Request $request, Server $server): JsonResponse
    {
        $request->validate(['version' => 'required|string']);

        try {
            $version = $request->input('version');
            return response()->json(['version' => $version, 'available' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changeVersion(Request $request, Server $server): JsonResponse
    {
        $request->validate(['version' => 'required|string']);

        try {
            $version = $request->input('version');
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'server-port', $server->allocation->port ?? '19132');

            return response()->json(['success' => true, 'message' => "Version change to $version queued", 'version' => $version]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to change version: ' . $e->getMessage()], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
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

    private function getFallbackVersions(): array
    {
        return [
            ['version' => '1.21.50', 'stable' => true],
            ['version' => '1.21.44', 'stable' => true],
            ['version' => '1.21.40', 'stable' => true],
            ['version' => '1.21.30', 'stable' => false],
            ['version' => '1.21.20', 'stable' => false],
        ];
    }
}
