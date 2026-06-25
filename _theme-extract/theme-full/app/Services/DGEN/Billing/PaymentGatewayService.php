<?php

namespace Pterodactyl\Services\DGEN\Billing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentGatewayService
{
    private string $stripeKey;
    private string $stripeSecret;
    private string $paypalClientId;
    private string $paypalClientSecret;
    private string $paypalMode;

    public function __construct()
    {
        $this->stripeKey = config('services.stripe.key', '');
        $this->stripeSecret = config('services.stripe.secret', '');
        $this->paypalClientId = config('services.paypal.client_id', '');
        $this->paypalClientSecret = config('services.paypal.client_secret', '');
        $this->paypalMode = config('services.paypal.mode', 'sandbox');
    }

    /**
     * Charge a user via the configured payment gateway.
     */
    public function charge(int $userId, float $amount, string $currency = 'USD', ?string $method = null): array
    {
        $gateway = config('billing.gateway', 'stripe');

        return match ($gateway) {
            'stripe' => $this->chargeStripe($userId, $amount, $currency, $method),
            'paypal' => $this->chargePayPal($userId, $amount, $currency),
            default => ['success' => false, 'error' => 'Unknown payment gateway'],
        };
    }

    /**
     * Process a Stripe payment.
     */
    private function chargeStripe(int $userId, float $amount, string $currency, ?string $paymentMethodId): array
    {
        if (empty($this->stripeSecret)) {
            return ['success' => false, 'error' => 'Stripe secret key not configured'];
        }

        try {
            $response = Http::withBasicAuth($this->stripeSecret, '')
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int) ($amount * 100),
                    'currency' => strtolower($currency),
                    'payment_method' => $paymentMethodId,
                    'confirm' => true,
                    'metadata' => ['user_id' => $userId],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'succeeded') {
                    return [
                        'success' => true,
                        'transaction_id' => $data['id'],
                        'amount' => $amount,
                    ];
                }

                return ['success' => false, 'error' => 'Payment not completed: ' . ($data['status'] ?? 'unknown')];
            }

            $error = $response->json('error', ['message' => 'Unknown error']);

            return ['success' => false, 'error' => $error['message']];
        } catch (\Exception $exception) {
            Log::error('Stripe payment failed', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Process a PayPal payment.
     */
    private function chargePayPal(int $userId, float $amount, string $currency): array
    {
        if (empty($this->paypalClientId) || empty($this->paypalClientSecret)) {
            return ['success' => false, 'error' => 'PayPal credentials not configured'];
        }

        try {
            $baseUrl = $this->paypalMode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            // Get access token
            $tokenResponse = Http::withBasicAuth($this->paypalClientId, $this->paypalClientSecret)
                ->post($baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$tokenResponse->successful()) {
                return ['success' => false, 'error' => 'Failed to get PayPal access token'];
            }

            $accessToken = $tokenResponse->json('access_token');

            // Create order
            $orderResponse = Http::withToken($accessToken)
                ->post($baseUrl . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                    ]],
                    'metadata' => [
                        'user_id' => $userId,
                    ],
                ]);

            if ($orderResponse->successful()) {
                $orderData = $orderResponse->json();

                return [
                    'success' => true,
                    'transaction_id' => $orderData['id'],
                    'approval_url' => collect($orderData['links'] ?? [])
                        ->firstWhere('rel', 'approve')['href'] ?? null,
                ];
            }

            return ['success' => false, 'error' => 'PayPal order creation failed'];
        } catch (\Exception $exception) {
            Log::error('PayPal payment failed', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Create a Stripe checkout session.
     */
    public function createStripeCheckoutSession(int $userId, array $items, string $successUrl, string $cancelUrl): array
    {
        if (empty($this->stripeKey)) {
            return ['success' => false, 'error' => 'Stripe key not configured'];
        }

        try {
            $lineItems = array_map(fn ($item) => [
                'price_data' => [
                    'currency' => strtolower(config('billing.currency', 'usd')),
                    'product_data' => ['name' => $item['name'] ?? 'Item'],
                    'unit_amount' => (int) ($item['price'] ?? 0) * 100,
                ],
                'quantity' => $item['quantity'] ?? 1,
            ], $items);

            $response = Http::withBasicAuth($this->stripeSecret, '')
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'payment_method_types' => ['card'],
                    'line_items' => $lineItems,
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => ['user_id' => $userId],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'session_id' => $response->json('id'),
                    'url' => $response->json('url'),
                ];
            }

            return ['success' => false, 'error' => 'Failed to create checkout session'];
        } catch (\Exception $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Handle a Stripe webhook event.
     */
    public function handleStripeWebhook(string $payload, string $sigHeader): array
    {
        try {
            $event = json_decode($payload, true);

            match ($event['type'] ?? '') {
                'payment_intent.succeeded' => $this->handlePaymentSuccess($event['data']['object'] ?? []),
                'charge.refunded' => $this->handleRefund($event['data']['object'] ?? []),
                default => null,
            };

            return ['success' => true];
        } catch (\Exception $exception) {
            Log::error('Stripe webhook handling failed', [
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Handle successful payment.
     */
    private function handlePaymentSuccess(array $paymentIntent): void
    {
        $userId = $paymentIntent['metadata']['user_id'] ?? null;
        $transactionId = $paymentIntent['id'] ?? null;

        if ($userId && $transactionId) {
            Log::info('Payment succeeded via webhook', [
                'user_id' => $userId,
                'transaction_id' => $transactionId,
            ]);
        }
    }

    /**
     * Handle a refund.
     */
    private function handleRefund(array $charge): void
    {
        Log::info('Charge refunded', [
            'charge_id' => $charge['id'] ?? 'unknown',
        ]);
    }
}
