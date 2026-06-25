<?php

namespace Pterodactyl\Listeners;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\AdminAuditLog;
use Pterodactyl\Models\AuditLog;

class HyperV2SecurityLogListener
{
    /**
     * Handle security-related events.
     */
    public function handle(string $eventName, array $data): void
    {
        try {
            match ($eventName) {
                'security:suspicious_activity' => $this->logSuspiciousActivity($data),
                'security:brute_force_attempt' => $this->logBruteForceAttempt($data),
                'security:unauthorized_access' => $this->logUnauthorizedAccess($data),
                'security:api_abuse' => $this->logApiAbuse($data),
                'security:permission_escalation' => $this->logPermissionEscalation($data),
                default => $this->logGenericSecurityEvent($eventName, $data),
            };
        } catch (\Throwable $e) {
            Log::error("Failed to log security event '{$eventName}': {$e->getMessage()}");
        }
    }

    /**
     * Log suspicious activity.
     */
    protected function logSuspiciousActivity(array $data): void
    {
        $ipAddress = $data['ip_address'] ?? 'unknown';
        $userId = $data['user_id'] ?? null;
        $description = $data['description'] ?? 'Suspicious activity detected';

        Log::warning("Suspicious activity from IP {$ipAddress}: {$description}");

        AuditLog::instance('security:suspicious', [
            'ip_address' => $ipAddress,
            'description' => $description,
            'metadata' => $data,
        ])->save();
    }

    /**
     * Log brute force attempt.
     */
    protected function logBruteForceAttempt(array $data): void
    {
        $ipAddress = $data['ip_address'] ?? 'unknown';
        $attempts = $data['attempts'] ?? 0;
        $targetEmail = $data['email'] ?? 'unknown';

        Log::warning(
            "Brute force attempt detected: {$attempts} failed attempts from IP {$ipAddress} targeting {$targetEmail}."
        );

        AdminAuditLog::create([
            'user_id' => $data['user_id'] ?? null,
            'event' => 'security:brute_force',
            'subevent' => 'attempt',
            'metadata' => [
                'ip_address' => $ipAddress,
                'attempts' => $attempts,
                'target_email' => $targetEmail,
            ],
        ]);
    }

    /**
     * Log unauthorized access attempt.
     */
    protected function logUnauthorizedAccess(array $data): void
    {
        $ipAddress = $data['ip_address'] ?? 'unknown';
        $resource = $data['resource'] ?? 'unknown';

        Log::warning("Unauthorized access attempt from IP {$ipAddress} to resource {$resource}.");

        AuditLog::instance('security:unauthorized', [
            'ip_address' => $ipAddress,
            'resource' => $resource,
            'metadata' => $data,
        ])->save();
    }

    /**
     * Log API abuse.
     */
    protected function logApiAbuse(array $data): void
    {
        $ipAddress = $data['ip_address'] ?? 'unknown';
        $apiKeyId = $data['api_key_id'] ?? null;
        $endpoint = $data['endpoint'] ?? 'unknown';

        Log::warning("API abuse detected from IP {$ipAddress} on endpoint {$endpoint}.");

        AuditLog::instance('security:api_abuse', [
            'ip_address' => $ipAddress,
            'api_key_id' => $apiKeyId,
            'endpoint' => $endpoint,
            'metadata' => $data,
        ])->save();
    }

    /**
     * Log permission escalation attempt.
     */
    protected function logPermissionEscalation(array $data): void
    {
        $userId = $data['user_id'] ?? null;
        $attemptedAction = $data['action'] ?? 'unknown';

        Log::warning("Permission escalation attempt by user #{$userId}: {$attemptedAction}.");

        AuditLog::instance('security:privilege_escalation', [
            'user_id' => $userId,
            'attempted_action' => $attemptedAction,
            'metadata' => $data,
        ])->save();
    }

    /**
     * Log a generic security event.
     */
    protected function logGenericSecurityEvent(string $eventName, array $data): void
    {
        Log::info("Security event '{$eventName}' occurred.", $data);

        AuditLog::instance("security:{$eventName}", $data)->save();
    }
}
