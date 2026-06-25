<?php

namespace Pterodactyl\Services\DGEN\Billing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;

class OrderService
{
    private const ORDER_STATUSES = [
        'pending',
        'processing',
        'completed',
        'failed',
        'cancelled',
        'refunded',
    ];

    public function __construct(
        private PaymentGatewayService $gatewayService,
        private BillingService $billingService,
    ) {
    }

    /**
     * Create a new order.
     */
    public function createOrder(User $user, array $items, array $paymentData = []): array
    {
        $subtotal = array_sum(array_map(fn ($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1), $items));

        $orderData = [
            'user_id' => $user->id,
            'order_number' => $this->generateOrderNumber(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'currency' => $paymentData['currency'] ?? config('billing.currency', 'USD'),
            'items' => $items,
            'payment_method' => $paymentData['method'] ?? null,
            'metadata' => $paymentData['metadata'] ?? [],
        ];

        try {
            $orderId = DB::table('orders')->insertGetId($orderData);
            $orderData['id'] = $orderId;

            Log::info('Order created', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'total' => $subtotal,
            ]);

            return $orderData;
        } catch (\Exception $exception) {
            Log::error('Failed to create order', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Process an order (charge payment, provision items).
     */
    public function processOrder(int $orderId): array
    {
        $order = DB::table('orders')->where('id', $orderId)->first();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        if ($order->status !== 'pending') {
            return ['success' => false, 'error' => 'Order is not in pending status'];
        }

        try {
            DB::table('orders')->where('id', $orderId)->update(['status' => 'processing']);

            // Process payment
            $paymentResult = $this->gatewayService->charge(
                $order->user_id,
                $order->total,
                $order->currency,
                $order->payment_method
            );

            if (!$paymentResult['success']) {
                DB::table('orders')->where('id', $orderId)->update(['status' => 'failed']);

                return ['success' => false, 'error' => $paymentResult['error'] ?? 'Payment failed'];
            }

            // Provision the ordered items
            $this->provisionItems($order);

            DB::table('orders')->where('id', $orderId)->update([
                'status' => 'completed',
                'transaction_id' => $paymentResult['transaction_id'] ?? null,
                'completed_at' => now(),
            ]);

            Log::info('Order processed successfully', ['order_id' => $orderId]);

            return ['success' => true, 'order_id' => $orderId];
        } catch (\Exception $exception) {
            DB::table('orders')->where('id', $orderId)->update(['status' => 'failed']);

            Log::error('Order processing failed', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(int $orderId): bool
    {
        try {
            return DB::table('orders')
                ->where('id', $orderId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]) > 0;
        } catch (\Exception $exception) {
            Log::error('Failed to cancel order', [
                'order_id' => $orderId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get orders for a user.
     */
    public function getUserOrders(User $user, int $limit = 50): array
    {
        return DB::table('orders')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get order details.
     */
    public function getOrder(int $orderId): ?array
    {
        return DB::table('orders')->where('id', $orderId)->first();
    }

    /**
     * Provision items after successful payment.
     */
    private function provisionItems(object $order): void
    {
        $items = json_decode($order->items, true) ?? [];

        foreach ($items as $item) {
            $type = $item['type'] ?? null;

            switch ($type) {
                case 'server':
                    // Provision a new server
                    Log::info('Provisioning server from order', [
                        'order_id' => $order->id,
                        'item' => $item,
                    ]);
                    break;

                case 'addon':
                    // Enable an addon
                    Log::info('Enabling addon from order', [
                        'order_id' => $order->id,
                        'item' => $item,
                    ]);
                    break;

                default:
                    Log::info('Processing generic order item', [
                        'order_id' => $order->id,
                        'item' => $item,
                    ]);
                    break;
            }
        }
    }

    /**
     * Generate a unique order number.
     */
    private function generateOrderNumber(): string
    {
        $prefix = config('billing.order_prefix', 'ORD');
        $nextNumber = DB::table('orders')->max('id') + 1;

        return sprintf('%s-%06d', $prefix, $nextNumber);
    }
}
