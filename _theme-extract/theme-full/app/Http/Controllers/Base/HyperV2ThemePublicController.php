<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

class HyperV2ThemePublicController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'theme' => 'hyperv2',
                'version' => config('dgen.theme.version', '2.0.0'),
                'name' => config('dgen.theme.name', 'HyperV2'),
                'author' => config('dgen.theme.author', 'VexyThemes'),
                'features' => [
                    'modern_ui' => true,
                    'dark_mode' => true,
                    'responsive' => true,
                    'pwa_support' => true,
                    'realtime' => true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
