<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

class LanguageController extends Controller
{
    public function available(Request $request): JsonResponse
    {
        try {
            $languages = [
                ['code' => 'en', 'name' => 'English', 'native' => 'English'],
                ['code' => 'es', 'name' => 'Spanish', 'native' => 'Español'],
                ['code' => 'fr', 'name' => 'French', 'native' => 'Français'],
                ['code' => 'de', 'name' => 'German', 'native' => 'Deutsch'],
                ['code' => 'pt', 'name' => 'Portuguese', 'native' => 'Português'],
                ['code' => 'nl', 'name' => 'Dutch', 'native' => 'Nederlands'],
                ['code' => 'ru', 'name' => 'Russian', 'native' => 'Русский'],
                ['code' => 'zh', 'name' => 'Chinese', 'native' => '中文'],
                ['code' => 'ja', 'name' => 'Japanese', 'native' => '日本語'],
                ['code' => 'ko', 'name' => 'Korean', 'native' => '한국어'],
                ['code' => 'tr', 'name' => 'Turkish', 'native' => 'Türkçe'],
                ['code' => 'pl', 'name' => 'Polish', 'native' => 'Polski'],
                ['code' => 'it', 'name' => 'Italian', 'native' => 'Italiano'],
                ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية'],
            ];

            $locale = $request->user()->language ?? config('app.locale', 'en');

            return response()->json([
                'languages' => $languages,
                'current' => $locale,
            ]);
        } catch (\Exception $e) {
            return response()->json(['languages' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function set(Request $request): JsonResponse
    {
        $request->validate(['language' => 'required|string|size:2']);

        try {
            $user = $request->user();
            $user->update(['language' => $request->input('language')]);

            return response()->json([
                'success' => true,
                'language' => $request->input('language'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
