<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\DGEN\Payment;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $orders = Payment::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['orders' => $orders]);
        } catch (\Exception $e) {
            return response()->json(['orders' => []], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|string',
            'billing_cycle' => 'required|string|in:monthly,quarterly,annually',
            'payment_method' => 'required|string|in:stripe,paypal,crypto',
        ]);

        try {
            $order = Payment::create([
                'user_id' => $request->user()->id,
                'amount' => $this->calculatePrice($request->input('plan_id'), $request->input('billing_cycle')),
                'currency' => config('dgen.billing.currency', 'USD'),
                'status' => 'pending',
                'gateway' => $request->input('payment_method'),
                'metadata' => [
                    'plan_id' => $request->input('plan_id'),
                    'billing_cycle' => $request->input('billing_cycle'),
                ],
            ]);

            return response()->json(['success' => true, 'order' => $order]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, string $orderId): JsonResponse
    {
        try {
            $order = Payment::where('user_id', $request->user()->id)->findOrFail($orderId);
            return response()->json(['order' => $order]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cancel(Request $request, string $orderId): JsonResponse
    {
        try {
            $order = Payment::where('user_id', $request->user()->id)->findOrFail($orderId);
            $order->update(['status' => 'cancelled']);

            return response()->json(['success' => true, 'message' => 'Order cancelled']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    private function calculatePrice(string $planId, string $cycle): float
    {
        $prices = config('dgen.billing.plans', []);
        $basePrice = $prices[$planId]['price'] ?? 9.99;

        return match ($cycle) {
            'monthly' => $basePrice,
            'quarterly' => $basePrice * 3 * 0.9,
            'annually' => $basePrice * 12 * 0.8,
            default => $basePrice,
        };
    }
}
