<?php

namespace Pterodactyl\Console\Commands\DGEN\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\DGEN\Payment;
use Pterodactyl\Models\User;

class AutoRenewalCommand extends Command
{
    protected $signature = 'dgen:billing:auto-renew
        {--dry-run : Process without charging}
        {--limit=50 : Maximum payments to process per run}
        {--notify : Send notifications for failed renewals}';

    protected $description = 'Process auto-renewal payments for servers with recurring billing enabled.';

    public function handle(): int
    {
        $this->output->title('Auto-Renewal Processing');

        $limit = (int) $this->option('limit');

        $pendingRenewals = DB::table('payments')
            ->where('status', 'pending')
            ->where('is_recurring', true)
            ->where('auto_renew', true)
            ->where('next_billing_date', '<=', CarbonImmutable::now())
            ->with('user')
            ->limit($limit)
            ->get();

        if ($pendingRenewals->isEmpty()) {
            $this->info('No auto-renewals due for processing.');

            return 0;
        }

        $this->info("Processing {$pendingRenewals->count()} auto-renewal(s)...");
        $this->newLine();

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $failedPayments = [];

        foreach ($pendingRenewals as $payment) {
            $result = $this->processRenewal($payment);

            match ($result) {
                'success' => $successCount++,
                'failed' => $failedCount++,
                'skipped' => $skippedCount++,
            };

            if ($result === 'failed') {
                $failedPayments[] = $payment;
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] No payments were actually processed.');
        } else {
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Successful', $successCount],
                    ['Failed', $failedCount],
                    ['Skipped', $skippedCount],
                ]
            );
        }

        if (!empty($failedPayments) && $this->option('notify')) {
            $this->sendFailureNotifications($failedPayments);
        }

        return $failedCount > 0 ? 1 : 0;
    }

    private function processRenewal(object $payment): string
    {
        $user = User::find($payment->user_id);

        if (!$user) {
            $this->error("  Payment #{$payment->id}: User not found, skipping.");
            return 'skipped';
        }

        $this->line("  Processing renewal for User #{$user->id} ({$user->email}) — $" . number_format($payment->amount, 2));

        if ($this->option('dry-run')) {
            $this->line("    <comment>[DRY-RUN]</comment> Would charge $" . number_format($payment->amount, 2));
            return 'success';
        }

        try {
            DB::beginTransaction();

            $gatewayResponse = $this->chargePayment($payment, $user);

            if ($gatewayResponse['success']) {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update([
                        'status' => 'completed',
                        'transaction_id' => $gatewayResponse['transaction_id'] ?? null,
                        'completed_at' => CarbonImmutable::now(),
                        'updated_at' => CarbonImmutable::now(),
                    ]);

                $this->updateNextBillingDate($payment);

                DB::commit();

                $this->line("    <info>Charged successfully</info>");
                return 'success';
            }

            DB::rollBack();

            $this->error("    Charge failed: " . ($gatewayResponse['error'] ?? 'Unknown error'));
            $this->handleFailedPayment($payment, $gatewayResponse['error'] ?? 'Unknown error');

            return 'failed';
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auto-renewal processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("    Processing error: " . $e->getMessage());

            return 'failed';
        }
    }

    private function chargePayment(object $payment, User $user): array
    {
        $gateway = config('billing.gateway', 'stripe');

        switch ($gateway) {
            case 'stripe':
                return $this->chargeStripe($payment, $user);
            case 'paypal':
                return $this->chargePaypal($payment, $user);
            default:
                return [
                    'success' => false,
                    'error' => "Unsupported billing gateway: $gateway",
                ];
        }
    }

    private function chargeStripe(object $payment, User $user): array
    {
        $stripeKey = config('billing.stripe.secret_key');

        if (!$stripeKey) {
            return ['success' => false, 'error' => 'Stripe API key not configured'];
        }

        try {
            $response = Http::withBasicAuth($stripeKey, '')
                ->post('https://api.stripe.com/v1/charges', [
                    'amount' => (int) ($payment->amount * 100),
                    'currency' => strtolower($payment->currency ?? 'usd'),
                    'customer' => $user->stripe_customer_id ?? null,
                    'description' => "Auto-renewal payment #{$payment->id}",
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'transaction_id' => $response->json('id'),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message') ?? 'Stripe charge failed',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function chargePaypal(object $payment, User $user): array
    {
        return [
            'success' => false,
            'error' => 'PayPal auto-renewal not yet implemented',
        ];
    }

    private function handleFailedPayment(object $payment, string $error): void
    {
        $retryCount = ($payment->retry_count ?? 0) + 1;
        $maxRetries = config('billing.auto_renew.max_retries', 3);

        DB::table('payments')
            ->where('id', $payment->id)
            ->update([
                'retry_count' => $retryCount,
                'last_retry_at' => CarbonImmutable::now(),
                'last_error' => $error,
                'updated_at' => CarbonImmutable::now(),
            ]);

        if ($retryCount >= $maxRetries) {
            DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'status' => 'failed',
                    'auto_renew' => false,
                ]);

            $this->warn("    Payment #{$payment->id} failed after $maxRetries retries. Auto-renewal disabled.");
        }
    }

    private function updateNextBillingDate(object $payment): void
    {
        $intervalDays = $payment->billing_interval_days ?? 30;

        DB::table('payments')
            ->where('id', $payment->id)
            ->update([
                'next_billing_date' => CarbonImmutable::now()->addDays($intervalDays),
                'updated_at' => CarbonImmutable::now(),
            ]);
    }

    private function sendFailureNotifications(array $failedPayments): void
    {
        $webhookUrl = config('dgen.discord.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $description = '';
        foreach ($failedPayments as $payment) {
            $description .= "• Payment #{$payment->id}: $" . number_format($payment->amount, 2) . "\n";
        }

        $payload = [
            'embeds' => [
                [
                    'title' => 'Auto-Renewal Payment Failures',
                    'description' => $description,
                    'color' => 0xFF0000,
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];

        try {
            Http::post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Failed to send payment failure notification', ['error' => $e->getMessage()]);
        }
    }
}
