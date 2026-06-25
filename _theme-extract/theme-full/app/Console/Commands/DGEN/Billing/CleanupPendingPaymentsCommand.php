<?php

namespace Pterodactyl\Console\Commands\DGEN\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupPendingPaymentsCommand extends Command
{
    protected $signature = 'dgen:billing:cleanup-payments
        {--age=48 : Age in hours after which a pending payment is considered stale}
        {--dry-run : Show what would be cleaned up without deleting}';

    protected $description = 'Clean up stale pending payments that were never completed or cancelled.';

    public function handle(): int
    {
        $ageHours = (int) $this->option('age');

        if ($ageHours <= 0) {
            $this->error('Age must be a positive integer (hours).');

            return 1;
        }

        $this->output->title('Pending Payment Cleanup');

        $cutoff = CarbonImmutable::now()->subHours($ageHours);

        $stalePayments = DB::table('payments')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($stalePayments->isEmpty()) {
            $this->info("No stale pending payments found older than {$ageHours} hours.");

            return 0;
        }

        $this->info("Found {$stalePayments->count()} stale pending payment(s) older than {$ageHours} hours:");
        $this->newLine();

        $this->table(
            ['ID', 'User ID', 'Amount', 'Currency', 'Created At', 'Age'],
            $stalePayments->map(fn ($p) => [
                $p->id,
                $p->user_id,
                '$' . number_format($p->amount, 2),
                $p->currency ?? 'USD',
                CarbonImmutable::parse($p->created_at)->toDateTimeString(),
                CarbonImmutable::parse($p->created_at)->diffForHumans(null, true),
            ])
        );

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('[DRY-RUN] No payments were cleaned up.');

            return 0;
        }

        if (!$this->confirm("Clean up {$stalePayments->count()} stale payment(s)?", true)) {
            $this->info('Cleanup cancelled.');

            return 0;
        }

        $expiredCount = 0;
        $deletedCount = 0;

        foreach ($stalePayments as $payment) {
            $hasTransaction = !empty($payment->transaction_id);

            if ($hasTransaction) {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update([
                        'status' => 'expired',
                        'updated_at' => CarbonImmutable::now(),
                    ]);
                $expiredCount++;
            } else {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->delete();
                $deletedCount++;
            }

            $this->line("  Payment #{$payment->id}: " . ($hasTransaction ? 'marked expired' : 'deleted'));
        }

        $this->newLine();
        $this->info("Cleanup complete: $expiredCount expired, $deletedCount deleted.");

        return 0;
    }
}
