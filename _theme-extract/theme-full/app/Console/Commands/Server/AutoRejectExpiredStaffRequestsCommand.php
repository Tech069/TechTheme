<?php

namespace Pterodactyl\Console\Commands\Server;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\StaffRequest;

class AutoRejectExpiredStaffRequestsCommand extends Command
{
    protected $signature = 'p:server:auto-reject-staff-requests
        {--dry-run : Show what would be rejected without applying changes}';

    protected $description = 'Automatically reject staff requests that have passed their expiration date.';

    public function handle(): int
    {
        $this->output->title('Auto-Reject Expired Staff Requests');

        $expiredRequests = StaffRequest::query()
            ->where('status', 'pending')
            ->where('expires_at', '<', CarbonImmutable::now())
            ->with(['server', 'staffUser'])
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('No expired staff requests found.');

            return 0;
        }

        $this->info("Found {$expiredRequests->count()} expired staff request(s):");
        $this->newLine();

        $this->table(
            ['ID', 'Staff User', 'Server', 'Description', 'Expires At'],
            $expiredRequests->map(fn ($r) => [
                $r->id,
                $r->staffUser?->username ?? "User #{$r->staff_user_id}",
                $r->server?->name ?? "Server #{$r->server_id}",
                substr($r->description, 0, 50) . (strlen($r->description) > 50 ? '...' : ''),
                CarbonImmutable::parse($r->expires_at)->toDateTimeString(),
            ])
        );

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('[DRY-RUN] No requests were rejected.');

            return 0;
        }

        if (!$this->confirm("Reject {$expiredRequests->count()} expired request(s)?", true)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $rejectedCount = 0;

        foreach ($expiredRequests as $request) {
            $request->update([
                'status' => 'auto_rejected',
                'updated_at' => CarbonImmutable::now(),
            ]);

            $staffName = $request->staffUser?->username ?? "User #{$request->staff_user_id}";
            $serverName = $request->server?->name ?? "Server #{$request->server_id}";

            $this->line("  <info>Rejected</info>: Request #{$request->id} ({$staffName} → {$serverName})");
            $rejectedCount++;

            Log::info('Staff request auto-rejected', [
                'request_id' => $request->id,
                'staff_user_id' => $request->staff_user_id,
                'server_id' => $request->server_id,
                'reason' => 'expired',
            ]);
        }

        $this->newLine();
        $this->info("Auto-rejected $rejectedCount expired staff request(s).");

        return 0;
    }
}
