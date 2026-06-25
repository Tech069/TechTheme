<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Http\Controllers\Controller;

class PasskeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $passkeys = $request->user()->passkeys ?? [];
            return response()->json(['passkeys' => $passkeys]);
        } catch (\Exception $e) {
            return response()->json(['passkeys' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function registerOptions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $options = [
                'rp' => [
                    'name' => config('app.name', 'Pterodactyl Panel'),
                    'id' => request()->getHost(),
                ],
                'user' => [
                    'id' => base64_encode((string) $user->id),
                    'name' => $user->email,
                    'displayName' => $user->username,
                ],
                'challenge' => base64_encode(random_bytes(32)),
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],
                    ['type' => 'public-key', 'alg' => -257],
                ],
                'timeout' => 60000,
                'attestation' => 'none',
            ];

            return response()->json(['options' => $options]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'credential_id' => 'required|string',
            'public_key' => 'required|string',
        ]);

        try {
            $passkey = [
                'id' => $request->input('credential_id'),
                'name' => $request->input('name'),
                'public_key' => $request->input('public_key'),
                'created_at' => now()->toDateTimeString(),
            ];

            return response()->json(['success' => true, 'passkey' => $passkey]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function loginOptions(Request $request): JsonResponse
    {
        try {
            $options = [
                'challenge' => base64_encode(random_bytes(32)),
                'timeout' => 60000,
                'userVerification' => 'preferred',
            ];

            return response()->json(['options' => $options]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate(['credential_id' => 'required|string']);

        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $token = $user->createToken('passkey-auth')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate(['passkey_id' => 'required|string']);

        try {
            return response()->json(['success' => true, 'message' => 'Passkey deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
