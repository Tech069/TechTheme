<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\UserIntegration;

class SSOLoginController extends Controller
{
    public function redirect(Request $request): JsonResponse
    {
        try {
            $provider = config('dgen.sso.provider', 'discord');
            $clientId = config("dgen.sso.{$provider}.client_id");
            $redirectUri = config("dgen.sso.{$provider}.redirect_uri");
            $scopes = config("dgen.sso.{$provider}.scopes", ['identify', 'email']);
            $state = csrf_token();

            $url = match ($provider) {
                'discord' => "https://discord.com/api/oauth2/authorize?" . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => implode(' ', $scopes),
                    'state' => $state,
                ]),
                'github' => "https://github.com/login/oauth/authorize?" . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => 'user:email',
                    'state' => $state,
                ]),
                default => '/auth/login',
            };

            return response()->json(['url' => $url, 'state' => $state]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string', 'state' => 'nullable|string']);

        try {
            $provider = config('dgen.sso.provider', 'discord');
            $code = $request->input('code');

            $tokenResponse = match ($provider) {
                'discord' => Http::post('https://discord.com/api/oauth2/token', [
                    'client_id' => config("dgen.sso.discord.client_id"),
                    'client_secret' => config("dgen.sso.discord.client_secret"),
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config("dgen.sso.discord.redirect_uri"),
                ]),
                'github' => Http::post('https://github.com/login/oauth/access_token', [
                    'client_id' => config("dgen.sso.github.client_id"),
                    'client_secret' => config("dgen.sso.github.client_secret"),
                    'code' => $code,
                ])->header('Accept', 'application/json'),
                default => null,
            };

            if (!$tokenResponse || !$tokenResponse->successful()) {
                return response()->json(['error' => 'Token exchange failed'], 422);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                return response()->json(['error' => 'No access token received'], 422);
            }

            return response()->json([
                'success' => true,
                'provider' => $provider,
                'access_token' => $accessToken,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function confirmLink(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        try {
            return response()->json(['success' => true, 'message' => 'SSO account linked']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function unlink(Request $request): JsonResponse
    {
        try {
            $provider = $request->input('provider', config('dgen.sso.provider', 'discord'));
            UserIntegration::where('user_id', $request->user()->id)
                ->where('provider', $provider)
                ->delete();

            return response()->json(['success' => true, 'message' => 'SSO account unlinked']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function dgenLink(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $integrations = UserIntegration::where('user_id', $user->id)->get();

            return response()->json([
                'linked' => $integrations->isNotEmpty(),
                'providers' => $integrations->pluck('provider')->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
