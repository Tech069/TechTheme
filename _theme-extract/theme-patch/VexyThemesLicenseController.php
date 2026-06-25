<?php
/**
 * VexyThemes License API Controller
 * 
 * Handles license key management from within the panel.
 * Called by the license gate page and the settings page.
 * 
 * Routes: POST /api/v2/vexythemes/license
 *         POST /api/v2/vexythemes/discord
 */

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VexyThemesLicenseController extends Controller
{
    const LICENSE_API = 'https://vt-panel-api.vercel.app';

    /**
     * POST /api/v2/vexythemes/license
     * 
     * Actions:
     * - save: Save a license key (from license gate or settings)
     * - remove: Remove the license key
     * - status: Get current license status (masked key)
     */
    public function handle(Request $request): JsonResponse
    {
        $action = $request->input('action', '');

        if ($action === 'status') {
            return $this->getStatus();
        }

        if ($action === 'save') {
            return $this->save($request);
        }

        if ($action === 'remove') {
            return $this->remove($request);
        }

        return response()->json(['error' => 'Unknown action'], 400);
    }

    private function getStatus(): JsonResponse
    {
        try {
            $key = DB::table('settings')->where('key', 'vexythemes_license_key')->first();
            $hasKey = $key && !empty($key->value);
            
            return response()->json([
                'has_key' => $hasKey,
                'masked_key' => $hasKey ? $this->maskKey($key->value) : null,
                'valid' => $this->checkCachedValidity(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['has_key' => false, 'valid' => false]);
        }
    }

    private function save(Request $request): JsonResponse
    {
        $key = strtoupper(trim($request->input('key', '')));
        
        if (empty($key) || strlen($key) < 20) {
            return response()->json(['error' => 'Invalid license key format'], 400);
        }

        $ip = $this->getServerIp();

        // Validate against API
        $response = @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode(['_endpoint' => 'license', 'action' => 'activate', 'key' => $key, 'ip' => $ip]),
                'timeout' => 10,
            ],
            'ssl' => ['verify_peer' => false],
        ]));

        if (!$response) {
            return response()->json(['error' => 'Failed to connect to license server'], 500);
        }

        $data = json_decode($response, true);

        if (!($data['success'] ?? false)) {
            return response()->json(['error' => $data['error'] ?? 'License validation failed'], 400);
        }

        // Save to database
        DB::table('settings')->updateOrCreate(
            ['key' => 'vexythemes_license_key'],
            ['value' => $key, 'updated_at' => now()]
        );

        // Clear cache
        DB::table('settings')->where('key', 'vexythemes_license_cache')->delete();

        return response()->json([
            'success' => true,
            'masked_key' => $this->maskKey($key),
            'message' => 'License activated successfully',
        ]);
    }

    private function remove(Request $request): JsonResponse
    {
        try {
            $key = DB::table('settings')->where('key', 'vexythemes_license_key')->first();
            
            if ($key && !empty($key->value)) {
                $ip = $this->getServerIp();
                
                // Deactivate on API side
                @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode(['_endpoint' => 'license', 'action' => 'deactivate', 'key' => $key->value, 'ip' => $ip]),
                        'timeout' => 10,
                    ],
                    'ssl' => ['verify_peer' => false],
                ]));

                // Remove from database
                DB::table('settings')->where('key', 'vexythemes_license_key')->delete();
                DB::table('settings')->where('key', 'vexythemes_license_cache')->delete();
            }

            return response()->json(['success' => true, 'message' => 'License removed']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to remove license'], 500);
        }
    }

    private function maskKey(string $key): string
    {
        // VEXY-XXXX-XXXX-XXXX-XXXX → VEXY-••••-••••-••••-••••
        if (strlen($key) > 5) {
            return substr($key, 0, 5) . str_repeat('•', strlen($key) - 5);
        }
        return str_repeat('•', strlen($key));
    }

    private function checkCachedValidity(): bool
    {
        try {
            $cache = DB::table('settings')->where('key', 'vexythemes_license_cache')->first();
            if ($cache && $cache->value) {
                $cached = json_decode($cache->value, true);
                if ($cached && isset($cached['valid']) && isset($cached['time'])) {
                    if (time() - $cached['time'] < 86400) {
                        return $cached['valid'];
                    }
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }

    private function getServerIp(): string
    {
        try {
            $ip = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 3]]));
            if ($ip) return trim($ip);
        } catch (\Throwable $e) {}
        return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    }
}
