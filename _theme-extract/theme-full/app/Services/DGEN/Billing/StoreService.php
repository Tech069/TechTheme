<?php

namespace Pterodactyl\Services\DGEN\Billing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StoreService
{
    private const PRODUCTS_CACHE_KEY = 'store:products';

    private const PRODUCTS_CACHE_TTL = 3600;

    public function __construct(
        private OrderService $orderService,
        private PaymentGatewayService $gatewayService,
    ) {
    }

    /**
     * List all available store products.
     */
    public function listProducts(string $category = null, bool $activeOnly = true): array
    {
        $cacheKey = self::PRODUCTS_CACHE_KEY . ':' . ($category ?? 'all') . ':' . ($activeOnly ? 'active' : 'all');

        return Cache::remember($cacheKey, self::PRODUCTS_CACHE_TTL, function () use ($category, $activeOnly) {
            $query = DB::table('store_products');

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            if ($category) {
                $query->where('category', $category);
            }

            return $query->orderByDesc('sort_order')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get a specific product.
     */
    public function getProduct(int $productId): ?array
    {
        return DB::table('store_products')->where('id', $productId)->first();
    }

    /**
     * Purchase a product.
     */
    public function purchase(int $userId, int $productId, array $paymentData = []): array
    {
        $product = $this->getProduct($productId);

        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        if (!$product->is_active) {
            return ['success' => false, 'error' => 'Product is not available'];
        }

        if ($product->stock !== null && $product->stock <= 0) {
            return ['success' => false, 'error' => 'Product is out of stock'];
        }

        // Create order
        $order = $this->orderService->createOrder(
            \Pterodactyl\Models\User::findOrFail($userId),
            [[
                'type' => $product->type ?? 'addon',
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'quantity' => 1,
                'product_id' => $productId,
            ]],
            $paymentData
        );

        // Process the order
        $result = $this->orderService->processOrder($order['id']);

        if ($result['success']) {
            // Decrease stock if applicable
            if ($product->stock !== null) {
                DB::table('store_products')
                    ->where('id', $productId)
                    ->decrement('stock');
            }

            // Clear product cache
            Cache::forget(self::PRODUCTS_CACHE_KEY . ':all:active');

            Log::info('Product purchased', [
                'user_id' => $userId,
                'product_id' => $productId,
                'order_id' => $order['id'],
            ]);
        }

        return $result;
    }

    /**
     * Create a new store product.
     */
    public function createProduct(array $data): int
    {
        return DB::table('store_products')->insertGetId(array_merge($data, [
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Update a store product.
     */
    public function updateProduct(int $productId, array $data): bool
    {
        $data['updated_at'] = now();

        return DB::table('store_products')
            ->where('id', $productId)
            ->update($data) > 0;
    }

    /**
     * Delete a store product.
     */
    public function deleteProduct(int $productId): bool
    {
        return DB::table('store_products')->where('id', $productId)->delete() > 0;
    }

    /**
     * Get store categories.
     */
    public function getCategories(): array
    {
        return DB::table('store_products')
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get purchase history for a user.
     */
    public function getUserPurchases(int $userId, int $limit = 50): array
    {
        return DB::table('store_purchases')
            ->join('store_products', 'store_purchases.product_id', '=', 'store_products.id')
            ->where('store_purchases.user_id', $userId)
            ->select('store_purchases.*', 'store_products.name as product_name')
            ->orderByDesc('store_purchases.created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
