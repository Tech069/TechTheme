<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\DGEN\Payment;
use Pterodactyl\Models\User;

class BillingController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $payments = Payment::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $totalSpent = Payment::where('user_id', $user->id)
                ->where('status', 'completed')
                ->sum('amount');

            $balance = $this->getUserBalance($user->id);

            return response()->json([
                'balance' => $balance,
                'total_spent' => $totalSpent,
                'recent_payments' => $payments,
                'currency' => config('dgen.billing.currency', 'USD'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function payments(Request $request): JsonResponse
    {
        try {
            $payments = Payment::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json(['payments' => $payments]);
        } catch (\Exception $e) {
            return response()->json(['payments' => []], 500);
        }
    }

    public function addFunds(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:1000',
            'gateway' => 'required|string|in:stripe,paypal,crypto',
        ]);

        try {
            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'amount' => $request->input('amount'),
                'currency' => config('dgen.billing.currency', 'USD'),
                'status' => 'pending',
                'gateway' => $request->input('gateway'),
            ]);

            return response()->json([
                'success' => true,
                'payment' => $payment,
                'checkout_url' => $this->getCheckoutUrl($payment),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function invoices(Request $request): JsonResponse
    {
        try {
            $payments = Payment::where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['invoices' => $payments]);
        } catch (\Exception $e) {
            return response()->json(['invoices' => []], 500);
        }
    }

    private function getUserBalance(int $userId): float
    {
        $totalPaid = Payment::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('amount');
        return (float) $totalPaid;
    }

    private function getCheckoutUrl(Payment $payment): ?string
    {
        return config('dgen.billing.checkout_base_url') . '/checkout/' . $payment->id;
    }
}
