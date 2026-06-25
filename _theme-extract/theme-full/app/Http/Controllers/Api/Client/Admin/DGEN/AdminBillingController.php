<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Admin\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\DGEN\Payment;
use Pterodactyl\Models\User;

class AdminBillingController extends Controller
{
    public function __construct()
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $payments = Payment::with('user')->orderBy('created_at', 'desc')->paginate(50);
            return response()->json(['payments' => $payments]);
        } catch (\Exception $e) {
            return response()->json(['payments' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $totalRevenue = Payment::where('status', 'completed')->sum('amount');
            $totalPayments = Payment::where('status', 'completed')->count();
            $pendingPayments = Payment::where('status', 'pending')->count();
            $recentPayments = Payment::where('created_at', '>=', now()->subDays(30))
                ->where('status', 'completed')
                ->sum('amount');

            return response()->json([
                'total_revenue' => $totalRevenue,
                'total_payments' => $totalPayments,
                'pending_payments' => $pendingPayments,
                'recent_revenue' => $recentPayments,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function userPayments(Request $request, int $userId): JsonResponse
    {
        try {
            $payments = Payment::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['payments' => $payments]);
        } catch (\Exception $e) {
            return response()->json(['payments' => []], 500);
        }
    }

    public function updateStatus(Request $request, int $paymentId): JsonResponse
    {
        $request->validate(['status' => 'required|string|in:pending,completed,failed,refunded']);

        try {
            $payment = Payment::findOrFail($paymentId);
            $payment->update(['status' => $request->input('status')]);

            return response()->json(['success' => true, 'payment' => $payment]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function refund(Request $request, int $paymentId): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            $payment->update(['status' => 'refunded']);

            return response()->json(['success' => true, 'message' => 'Payment refunded']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
