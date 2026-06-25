<?php

namespace Pterodactyl\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Pterodactyl\Mail\HyperV2SecurityAlertMail;
use Pterodactyl\Models\User;

class SendHyperV2SecurityAlert extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $alertType,
        public array $metadata = [],
    ) {
        $this->queue = 'notifications';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning("Cannot send security alert: User #{$this->userId} not found.");
            return;
        }

        if (empty($user->email)) {
            Log::warning("Cannot send security alert to user #{$this->userId}: no email address.");
            return;
        }

        try {
            Mail::to($user->email)->send(new HyperV2SecurityAlertMail(
                user: $user,
                alertType: $this->alertType,
                metadata: $this->metadata,
            ));

            Log::info("Security alert ({$this->alertType}) sent to user #{$this->userId}.");
        } catch (\Throwable $e) {
            Log::error("Failed to send security alert to user #{$this->userId}: {$e->getMessage()}");
        }
    }
}
