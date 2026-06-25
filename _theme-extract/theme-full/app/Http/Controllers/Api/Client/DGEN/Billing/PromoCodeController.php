<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\PromoCode;

class PromoCodeController extends Controller
{
    public function redeem(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:191']);

        try {
            $code = strtoupper($request->input('code'));
            $promo = PromoCode::where('code', $code)->first();

            if (!$promo) {
                return response()->json(['error' => 'Invalid promo code'], 422);
            }

            if ($promo->expires_at && $promo->expires_at->isPast()) {
                return response()->json(['error' => 'Promo code has expired'], 422);
            }

            return response()->json([
                'success' => true,
                'discount_percent' => $promo->discount_percent,
                'message' => "Promo code applied: {$promo->discount_percent}% discount",
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function validate(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        try {
            $code = strtoupper($request->input('code'));
            $promo = PromoCode::where('code', $code)->first();

            $valid = $promo
                && (!$promo->expires_at || $promo->expires_at->isFuture())
                && ($promo->max_uses === null || $promo->current_uses < $promo->max_uses);

            return response()->json([
                'valid' => $valid,
                'discount_percent' => $valid ? $promo->discount_percent : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['valid' => false], 500);
        }
    }
}
