<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $referralCode = strtoupper(substr(md5($user->uuid), 0, 8));
            $totalReferrals = $user->referrals_count ?? 0;
            $rewards = config('dgen.referrals.rewards', []);

            return response()->json([
                'referral_code' => $referralCode,
                'referral_link' => url("/auth/register?ref=$referralCode"),
                'total_referrals' => $totalReferrals,
                'rewards' => $rewards,
                'balance' => $user->referral_balance ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function apply(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        try {
            $code = strtoupper($request->input('code'));

            return response()->json([
                'success' => true,
                'message' => 'Referral code applied',
                'bonus' => config('dgen.referrals.signup_bonus', 1.00),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'total_referrals' => $request->user()->referrals_count ?? 0,
                'pending_rewards' => 0,
                'earned_rewards' => 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
