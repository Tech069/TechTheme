<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;

class WemxSsoController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $wemxUrl = config('dgen.wemx.url');
            $apiKey = config('dgen.wemx.api_key');
            $panelUrl = config('app.url');

            if (!$wemxUrl || !$apiKey) {
                return response()->json(['error' => 'WemX SSO not configured'], 422);
            }

            $url = "$wemxUrl/api/sso/redirect?" . http_build_query([
                'panel_url' => $panelUrl,
                'api_key' => $apiKey,
            ]);

            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'user_id' => 'required|integer',
            'email' => 'required|email',
            'username' => 'required|string',
        ]);

        try {
            $wemxUrl = config('dgen.wemx.url');
            $apiKey = config('dgen.wemx.api_key');

            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Accept' => 'application/json',
            ])->post("$wemxUrl/api/sso/verify", [
                'token' => $request->input('token'),
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'user' => [
                        'id' => $request->input('user_id'),
                        'email' => $request->input('email'),
                        'username' => $request->input('username'),
                    ],
                ]);
            }

            return response()->json(['error' => 'Token verification failed'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
