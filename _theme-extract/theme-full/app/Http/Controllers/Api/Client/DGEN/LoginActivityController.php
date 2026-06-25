<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\UserLoginHistory;

class LoginActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $activities = UserLoginHistory::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json(['activities' => $activities]);
        } catch (\Exception $e) {
            return response()->json(['activities' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->validate(['activity_id' => 'required|integer|exists:user_login_histories,id']);

        try {
            $activity = UserLoginHistory::where('user_id', $request->user()->id)
                ->findOrFail($request->input('activity_id'));

            $activity->delete();

            return response()->json(['success' => true, 'message' => 'Login session revoked']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
