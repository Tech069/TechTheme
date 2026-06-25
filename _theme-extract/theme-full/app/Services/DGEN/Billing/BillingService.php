<?php

namespace Pterodactyl\Services\DGEN\Billing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;

class BillingService
{
    private const INVOICE_STATUSES = [
        'pending',
        'paid',
        'overdue',
        'cancelled',
        'refunded',
    ];

    public function __construct(
        private PaymentGatewayService $gatewayService,
    ) {
    }

    /**
     * Create a new invoice for a user.
     */
    public function createInvoice(User $user, array $items, array $options = []): array
    {
        $subtotal = 0;
        $lineItems = [];

        foreach ($items as $item) {
            $lineTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            $subtotal += $lineTotal;

            $lineItems[] = [
                'description' => $item['description'] ?? '',
                'price' => $item['price'] ?? 0,
                'quantity' => $item['quantity'] ?? 1,
                'total' => $lineTotal,
            ];
        }

        $taxRate = $options['tax_rate'] ?? config('billing.tax_rate', 0);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = $subtotal + $taxAmount;

        $invoiceData = [
            'user_id' => $user->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => $options['currency'] ?? config('billing.currency', 'USD'),
            'items' => $lineItems,
            'notes' => $options['notes'] ?? null,
            'due_date' => $options['due_date'] ?? now()->addDays(30)->toDateString(),
            'paid_at' => null,
        ];

        try {
            $invoiceId = DB::table('invoices')->insertGetId($invoiceData);

            $invoiceData['id'] = $invoiceId;

            Log::info('Invoice created', [
                'invoice_id' => $invoiceId,
                'user_id' => $user->id,
                'total' => $total,
            ]);

            return $invoiceData;
        } catch (\Exception $exception) {
            Log::error('Failed to create invoice', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Check the payment status of an invoice.
     */
    public function getPaymentStatus(int $invoiceId): array
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

        if (!$invoice) {
            return ['status' => 'not_found'];
        }

        return [
            'invoice_id' => $invoice->id,
            'status' => $invoice->status,
            'total' => $invoice->total,
            'paid_at' => $invoice->paid_at,
            'due_date' => $invoice->due_date,
        ];
    }

    /**
     * Mark an invoice as paid.
     */
    public function markAsPaid(int $invoiceId, string $transactionId = null): bool
    {
        try {
            DB::table('invoices')
                ->where('id', $invoiceId)
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'transaction_id' => $transactionId,
                ]);

            Log::info('Invoice marked as paid', [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionId,
            ]);

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to mark invoice as paid', [
                'invoice_id' => $invoiceId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cancel an invoice.
     */
    public function cancelInvoice(int $invoiceId): bool
    {
        try {
            DB::table('invoices')
                ->where('id', $invoiceId)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to cancel invoice', [
                'invoice_id' => $invoiceId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all invoices for a user.
     */
    public function getUserInvoices(User $user, int $limit = 50, int $offset = 0): array
    {
        return DB::table('invoices')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Check if a user has any overdue invoices.
     */
    public function hasOverdueInvoices(User $user): bool
    {
        return DB::table('invoices')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->exists();
    }

    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = config('billing.invoice_prefix', 'INV');
        $nextNumber = DB::table('invoices')->max('id') + 1;

        return sprintf('%s-%06d', $prefix, $nextNumber);
    }
}
