<?php

namespace Pterodactyl\Http\Controllers\Api\Public;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

class PublicAddonSettingsController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $settings = [
                'theme' => config('dgen.theme.name', 'hyperv2'),
                'version' => config('dgen.theme.version', '2.0.0'),
                'features' => config('dgen.features', []),
                'branding' => [
                    'name' => config('app.name', 'Pterodactyl Panel'),
                    'logo' => config('dgen.branding.logo', null),
                    'favicon' => config('dgen.branding.favicon', null),
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
            $value = config("dgen.public.$key");
            return response()->json(['key' => $key, 'value' => $value]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
