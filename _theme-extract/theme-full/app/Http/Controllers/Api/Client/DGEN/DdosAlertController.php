<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\DGEN\DdosAlertEvent;

class DdosAlertController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        try {
            $totalAttacks = DdosAlertEvent::count();
            $activeAttacks = DdosAlertEvent::whereNull('resolved_at')->count();
            $recentAttacks = DdosAlertEvent::where('detected_at', '>=', now()->subDays(7))->count();
            $lastAttack = DdosAlertEvent::latest('detected_at')->first();

            return response()->json([
                'total_attacks' => $totalAttacks,
                'active_attacks' => $activeAttacks,
                'recent_attacks_7d' => $recentAttacks,
                'last_attack' => $lastAttack?->detected_at,
                'status' => $activeAttacks > 0 ? 'under_attack' : 'clear',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function attacks(Request $request): JsonResponse
    {
        try {
            $attacks = DdosAlertEvent::with('node', 'server')
                ->orderBy('detected_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json(['attacks' => $attacks]);
        } catch (\Exception $e) {
            return response()->json(['attacks' => []], 500);
        }
    }

    public function charts(Request $request): JsonResponse
    {
        try {
            $daily = DdosAlertEvent::where('detected_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(detected_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json(['chart_data' => $daily]);
        } catch (\Exception $e) {
            return response()->json(['chart_data' => []], 500);
        }
    }

    public function syncNow(Request $request): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'message' => 'DDoS alert sync triggered']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
