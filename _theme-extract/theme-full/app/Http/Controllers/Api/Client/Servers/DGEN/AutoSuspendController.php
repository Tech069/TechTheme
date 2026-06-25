<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class AutoSuspendController extends Controller
{
    public function getExpiry(Request $request, Server $server): JsonResponse
    {
        try {
            $expiry = $server->expires_at ?? $server->getAttributes()['expires_at'] ?? null;

            return response()->json([
                'has_expiry' => $expiry !== null,
                'expires_at' => $expiry,
                'is_expired' => $expiry && now()->gt($expiry),
                'days_remaining' => $expiry ? max(0, now()->diffInDays($expiry, false)) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setExpiry(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'expires_at' => 'required|date|after:now',
        ]);

        try {
            $server->update(['expires_at' => $request->input('expires_at')]);

            return response()->json([
                'success' => true,
                'expires_at' => $request->input('expires_at'),
                'message' => 'Expiry date set successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to set expiry: ' . $e->getMessage()], 500);
        }
    }

    public function removeExpiry(Request $request, Server $server): JsonResponse
    {
        try {
            $server->update(['expires_at' => null]);

            return response()->json(['success' => true, 'message' => 'Expiry removed']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to remove expiry: ' . $e->getMessage()], 500);
        }
    }
}
