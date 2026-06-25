<?php

namespace Pterodactyl\Http\Controllers\Api\Public;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

class WingsAddonSettingsController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $settings = [
                'addon_version' => config('dgen.wings.addon_version', '1.0.0'),
                'features_enabled' => config('dgen.wings.features', []),
                'compatibility' => [
                    'min_wings_version' => config('dgen.wings.min_version', '1.11.0'),
                    'panel_version' => config('app.version', '1.0.0'),
                ],
                'endpoints' => [
                    'health' => '/api/public/wings/health',
                    'settings' => '/api/public/wings/settings',
                ],
            ];

            return response()->json($settings);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, string $key): JsonResponse
    {
        try {
            $value = config("dgen.wings.$key");
            return response()->json(['key' => $key, 'value' => $value]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
