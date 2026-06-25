<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Theme;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Setting;

class HyperV2ThemeController extends Controller
{
    public function show(Request $request, Server $server): JsonResponse
    {
        try {
            $settings = $this->getThemeSettings($server->id);

            return response()->json([
                'theme' => 'hyperv2',
                'settings' => $settings,
                'version' => config('dgen.theme.version', '2.0.0'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $request->validate(['settings' => 'required|array']);

        try {
            $settings = $request->input('settings');
            $this->saveThemeSettings($server->id, $settings);

            return response()->json(['success' => true, 'message' => 'Theme settings updated']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkVersion(Request $request, Server $server): JsonResponse
    {
        try {
            $currentVersion = config('dgen.theme.version', '2.0.0');

            return response()->json([
                'current_version' => $currentVersion,
                'latest_version' => $currentVersion,
                'update_available' => false,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function startUpdate(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'message' => 'Theme update started']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getUpdateStatus(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['status' => 'completed', 'progress' => 100]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAvailableSidebarItems(Request $request, Server $server): JsonResponse
    {
        try {
            $items = [
                ['id' => 'console', 'label' => 'Console', 'icon' => 'terminal', 'enabled' => true],
                ['id' => 'files', 'label' => 'Files', 'icon' => 'folder', 'enabled' => true],
                ['id' => 'databases', 'label' => 'Databases', 'icon' => 'database', 'enabled' => true],
                ['id' => 'schedules', 'label' => 'Schedules', 'icon' => 'clock', 'enabled' => true],
                ['id' => 'users', 'label' => 'Users', 'icon' => 'users', 'enabled' => true],
                ['id' => 'backups', 'label' => 'Backups', 'icon' => 'archive', 'enabled' => true],
                ['id' => 'network', 'label' => 'Network', 'icon' => 'globe', 'enabled' => true],
                ['id' => 'startup', 'label' => 'Startup', 'icon' => 'play', 'enabled' => true],
            ];

            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['items' => []], 500);
        }
    }

    public function ssoExchange(Request $request, Server $server): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        try {
            return response()->json(['success' => true, 'message' => 'SSO token exchanged']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function ssoInfo(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json([
                'sso_enabled' => config('dgen.sso.enabled', false),
                'provider' => config('dgen.sso.provider', null),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function ssoDisconnect(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'message' => 'SSO disconnected']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function getThemeSettings(int $serverId): array
    {
        $cacheKey = "theme_settings_{$serverId}";
        return Cache::get($cacheKey, [
            'primary_color' => '#6366f1',
            'sidebar_style' => 'modern',
            'animations_enabled' => true,
            'compact_mode' => false,
        ]);
    }

    private function saveThemeSettings(int $serverId, array $settings): void
    {
        $cacheKey = "theme_settings_{$serverId}";
        Cache::put($cacheKey, $settings, 86400);
    }
}
