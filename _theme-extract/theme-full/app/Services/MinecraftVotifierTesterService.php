<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Log;

class MinecraftVotifierTesterService
{
    private const TEST_TIMEOUT = 10;

    private const V2_MAGIC = 'votifier';

    public function __construct()
    {
    }

    /**
     * Test a Votifier plugin connection and return the result.
     */
    public function testConnection(string $host, int $port, ?string $token = null): array
    {
        $result = [
            'success' => false,
            'host' => $host,
            'port' => $port,
            'version' => null,
            'error' => null,
            'response' => null,
        ];

        try {
            $socket = @fsocknew('tcp', $host, $port);

            if ($socket === false) {
                $result['error'] = sprintf('Could not connect to %s:%d', $host, $port);

                return $result;
            }

            stream_set_timeout($socket, self::TEST_TIMEOUT);

            // Read the initial greeting from the Votifier service
            $greeting = fread($socket, 256);

            if ($greeting === false || empty($greeting)) {
                $result['error'] = 'No greeting received from Votifier service';
                fclose($socket);

                return $result;
            }

            $result['response'] = trim($greeting);

            // Detect Votifier version from greeting
            if (str_contains($greeting, 'VOTIFIER 2')) {
                $result['version'] = 2;
                $this->testV2Protocol($socket, $token, $result);
            } else {
                $result['version'] = 1;
                $this->testV1Protocol($socket, $token, $result);
            }

            fclose($socket);
        } catch (\Exception $exception) {
            Log::error('Votifier test failed', [
                'host' => $host,
                'port' => $port,
                'error' => $exception->getMessage(),
            ]);

            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Test Votifier V1 protocol.
     */
    private function testV1Protocol($socket, ?string $token, array &$result): void
    {
        try {
            // V1 protocol sends votes in plain text format
            $testVote = "VOTE\nTestPlugin\nTestPlayer\nhttp://example.com\n" . time() . "\n";

            fwrite($socket, $testVote);

            $response = fread($socket, 256);

            if ($response !== false && str_contains($response, 'OK')) {
                $result['success'] = true;
            } else {
                $result['error'] = 'V1 protocol test failed: unexpected response';
            }
        } catch (\Exception $exception) {
            $result['error'] = 'V1 protocol test error: ' . $exception->getMessage();
        }
    }

    /**
     * Test Votifier V2 protocol.
     */
    private function testV2Protocol($socket, ?string $token, array &$result): void
    {
        try {
            if (empty($token)) {
                $result['error'] = 'Token is required for Votifier V2';

                return;
            }

            // V2 protocol uses a challenge-response system
            // Read the challenge token from the server
            $challengeData = fread($socket, 1024);

            // Build a test vote payload
            $payload = json_encode([
                'version' => 2,
                'timestamp' => time(),
                'service' => 'HyperPanel Test',
                'username' => 'TestPlayer',
                'uuid' => '00000000-0000-0000-0000-000000000000',
            ]);

            $signature = $this->signPayload($payload, $token);

            $message = self::V2_MAGIC . "\n";
            $message .= base64_encode($payload) . "\n";
            $message .= base64_encode($signature) . "\n";

            fwrite($socket, $message);

            $response = fread($socket, 256);

            if ($response !== false && str_contains($response, 'OK')) {
                $result['success'] = true;
            } else {
                $result['error'] = 'V2 protocol test failed: ' . trim($response ?? '');
            }
        } catch (\Exception $exception) {
            $result['error'] = 'V2 protocol test error: ' . $exception->getMessage();
        }
    }

    /**
     * Sign a payload using the provided token.
     */
    private function signPayload(string $payload, string $token): string
    {
        // Simple HMAC-SHA256 signing using the token
        return hash_hmac('sha256', $payload, $token);
    }

    /**
     * Quick connectivity check (TCP only, no protocol test).
     */
    public function pingServer(string $host, int $port): bool
    {
        try {
            $socket = @fsocknew('tcp', $host, $port, $errno, $errstr, self::TEST_TIMEOUT);

            if ($socket === false) {
                return false;
            }

            fclose($socket);

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }
}
