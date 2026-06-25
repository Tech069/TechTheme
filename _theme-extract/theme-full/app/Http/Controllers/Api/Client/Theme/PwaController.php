<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Theme;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class PwaController extends Controller
{
    public function manifest(Request $request, Server $server): JsonResponse
    {
        try {
            $manifest = [
                'name' => config('app.name', 'Pterodactyl Panel'),
                'short_name' => config('dgen.pwa.short_name', 'Panel'),
                'description' => config('dgen.pwa.description', 'Server Management Panel'),
                'start_url' => '/',
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => config('dgen.pwa.theme_color', '#6366f1'),
                'orientation' => 'any',
                'icons' => [
                    [
                        'src' => '/favicon.ico',
                        'sizes' => '64x64',
                        'type' => 'image/x-icon',
                    ],
                    [
                        'src' => '/img/icons/android-chrome-192x192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ],
                    [
                        'src' => '/img/icons/android-chrome-512x512.png',
                        'sizes' => '512x512',
                        'type' => 'image/png',
                    ],
                ],
            ];

            return response()->json($manifest)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function serviceWorkerConfig(Request $request, Server $server): JsonResponse
    {
        try {
            $config = [
                'enabled' => config('dgen.pwa.enabled', true),
                'cache_strategy' => config('dgen.pwa.cache_strategy', 'network_first'),
                'offline_page' => '/offline',
                'precache_paths' => [
                    '/',
                    '/css/app.css',
                    '/js/app.js',
                ],
            ];

            return response()->json($config);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
