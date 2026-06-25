<?php

namespace Pterodactyl\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\WipeSchedule;

class CheckScheduledWipes extends Command
{
    protected $signature = 'p:wipes:check {--dry-run : Show what would be executed without actually running}';

    protected $description = 'Check for scheduled wipes that are due and display their status.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $dueSchedules = WipeSchedule::query()
            ->where('is_active', true)
            ->where('next_run_at', '<=', $now)
            ->with('server')
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No scheduled wipes are currently due for execution.');

            return 0;
        }

        $this->info("Found {$dueSchedules->count()} scheduled wipe(s) due for execution:");
        $this->newLine();

        $this->table(
            ['ID', 'Server', 'Schedule Name', 'Type', 'Next Run', 'Last Run'],
            $dueSchedules->map(fn (WipeSchedule $schedule) => [
                $schedule->id,
                $schedule->server?->name ?? 'Unknown (#' . $schedule->server_id . ')',
                $schedule->name,
                $schedule->schedule_type,
                $schedule->next_run_at?->toDateTimeString() ?? 'N/A',
                $schedule->last_run_at?->toDateTimeString() ?? 'Never',
            ])
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode: no wipes will be processed.');

            return 0;
        }

        if (!$this->confirm("Do you want to process these {$dueSchedules->count()} wipe(s)?", false)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->call('p:wipes:process');

        return 0;
    }
}
