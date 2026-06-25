<?php
namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VexyThemesDiscordController extends Controller
{
    const LICENSE_API = 'https://vt-panel-api.vercel.app';

    /**
     * POST /api/v2/vexythemes/discord
     * Actions: connect, callback
     */
    public function handle(Request $request): JsonResponse
    {
        $action = $request->input('action', '');

        if ($action === 'connect') {
            // Get Discord OAuth URL from API
            $response = @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        '_endpoint' => 'discord',
                        'action' => 'connect',
                        'key' => $request->input('key', ''),
                    ]),
                    'timeout' => 10,
                ],
                'ssl' => ['verify_peer' => false],
            ]));

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['url'])) {
                    return response()->json(['url' => $data['url']]);
                }
            }

            return response()->json(['error' => 'Failed to generate Discord auth URL'], 500);
        }

        if ($action === 'callback') {
            // Forward code to API
            $response = @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([
                        '_endpoint' => 'discord',
                        'action' => 'callback',
                        'code' => $request->input('code', ''),
                        'key' => $request->input('key', ''),
                    ]),
                    'timeout' => 10,
                ],
                'ssl' => ['verify_peer' => false],
            ]));

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['discord'])) {
                    return response()->json(['success' => true, 'discord' => $data['discord']]);
                }
            }

            return response()->json(['error' => 'Discord authentication failed'], 500);
        }

        return response()->json(['error' => 'Unknown action'], 400);
    }
}
