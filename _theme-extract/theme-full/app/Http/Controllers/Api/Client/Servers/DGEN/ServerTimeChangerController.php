<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class ServerTimeChangerController extends Controller
{
    public function status(Request $request, Server $server): JsonResponse
    {
        try {
            $props = $this->getServerProperties($server->server_data_directory);
            $timezone = $props['level-seed'] ?? null;

            $serverDir = $server->server_data_directory;
            $timezoneFile = "$serverDir/.timezone";

            $currentTz = 'UTC';
            if (file_exists($timezoneFile)) {
                $currentTz = trim(file_get_contents($timezoneFile));
            }

            return response()->json([
                'current_timezone' => $currentTz,
                'supports_timezone' => true,
                'system_timezone' => config('app.timezone', 'UTC'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setTimezone(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'timezone' => 'required|string|max:100',
        ]);

        try {
            $timezone = $request->input('timezone');

            $validTimezones = \DateTimeZone::listIdentifiers();
            if (!in_array($timezone, $validTimezones)) {
                return response()->json(['error' => 'Invalid timezone'], 422);
            }

            $timezoneFile = $server->server_data_directory . '/.timezone';
            file_put_contents($timezoneFile, $timezone);

            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'level-seed', $timezone);

            return response()->json(['success' => true, 'timezone' => $timezone, 'message' => "Timezone set to $timezone"]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to set timezone: ' . $e->getMessage()], 500);
        }
    }

    private function getServerProperties(string $path): array
    {
        $file = "$path/server.properties";
        $config = [];
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
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
}
