<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class FastDLNginxController extends Controller
{
    public function setup(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:191',
            'port' => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            $domain = $request->input('domain');
            $port = $request->input('port', 8080);
            $serverDir = $server->server_data_directory;
            $nginxDir = "$serverDir/.nginx";

            if (!is_dir($nginxDir)) {
                mkdir($nginxDir, 0755, true);
            }

            $config = "server {
    listen $port;
    server_name $domain;

    location / {
        alias $serverDir/;
        autoindex on;
        autoindex_exact_size off;
        autoindex_localtime on;

        add_header Access-Control-Allow-Origin *;
        add_header Cache-Control \"public, max-age=3600\";
    }
}
";

            file_put_contents("$nginxDir/fastdl.conf", $config);

            return response()->json([
                'success' => true,
                'message' => 'Nginx FastDL configuration created',
                'config_path' => "$nginxDir/fastdl.conf",
                'domain' => $domain,
                'port' => $port,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to setup FastDL: ' . $e->getMessage()], 500);
        }
    }

    public function remove(Request $request, Server $server): JsonResponse
    {
        try {
            $configFile = $server->server_data_directory . '/.nginx/fastdl.conf';

            if (file_exists($configFile)) {
                unlink($configFile);
            }

            return response()->json(['success' => true, 'message' => 'FastDL nginx config removed']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function status(Request $request, Server $server): JsonResponse
    {
        try {
            $configFile = $server->server_data_directory . '/.nginx/fastdl.conf';
            $exists = file_exists($configFile);

            return response()->json([
                'configured' => $exists,
                'config_path' => $exists ? $configFile : null,
                'domain' => $exists ? $this->extractDomain($configFile) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function extractDomain(string $configFile): ?string
    {
        $content = file_get_contents($configFile);
        if (preg_match('/server_name\s+(.+?);/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
