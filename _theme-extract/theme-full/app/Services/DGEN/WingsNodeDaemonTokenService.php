<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\Node;

class WingsNodeDaemonTokenService
{
    public function __construct(
        private Encrypter $encrypter,
    ) {
    }

    /**
     * Generate a new daemon token pair for a node.
     */
    public function generateTokenPair(): array
    {
        $tokenId = Str::random(Node::DAEMON_TOKEN_ID_LENGTH);
        $token = Str::random(Node::DAEMON_TOKEN_LENGTH);

        return [
            'token_id' => $tokenId,
            'token' => $token,
            'encrypted_token' => $this->encrypter->encrypt($token),
        ];
    }

    /**
     * Rotate the daemon token for a node.
     */
    public function rotateToken(Node $node): array
    {
        $newTokenPair = $this->generateTokenPair();

        $node->update([
            'daemon_token_id' => $newTokenPair['token_id'],
            'daemon_token' => $newTokenPair['encrypted_token'],
        ]);

        Log::info('Node daemon token rotated', [
            'node_id' => $node->id,
        ]);

        return $newTokenPair;
    }

    /**
     * Verify a daemon token against a node.
     */
    public function verifyToken(Node $node, string $tokenId, string $token): bool
    {
        if ($node->daemon_token_id !== $tokenId) {
            return false;
        }

        $decryptedToken = $this->encrypter->decrypt($node->daemon_token);

        return hash_equals($decryptedToken, $token);
    }

    /**
     * Get the decrypted token for a node.
     */
    public function getDecryptedToken(Node $node): string
    {
        return $node->getDecryptedKey();
    }

    /**
     * Generate a configuration array for a Wings node.
     */
    public function getNodeConfig(Node $node): array
    {
        return [
            'uuid' => $node->uuid,
            'token_id' => $node->daemon_token_id,
            'token' => $this->getDecryptedToken($node),
            'api' => [
                'host' => '0.0.0.0',
                'port' => $node->daemonListen,
                'ssl' => [
                    'enabled' => (!$node->behind_proxy && $node->scheme === 'https'),
                    'cert' => '/etc/letsencrypt/live/' . Str::lower($node->fqdn) . '/fullchain.pem',
                    'key' => '/etc/letsencrypt/live/' . Str::lower($node->fqdn) . '/privkey.pem',
                ],
            ],
            'system' => [
                'data' => $node->daemonBase,
                'sftp' => [
                    'bind_port' => $node->daemonSFTP,
                ],
            ],
        ];
    }

    /**
     * Validate token format.
     */
    public function isValidTokenFormat(string $tokenId, string $token): bool
    {
        return strlen($tokenId) === Node::DAEMON_TOKEN_ID_LENGTH
            && strlen($token) === Node::DAEMON_TOKEN_LENGTH
            && ctype_alnum($tokenId)
            && ctype_alnum($token);
    }
}
