<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HyperV2DataSanitizerService
{
    private const FORBIDDEN_PATTERNS = [
        '/\b(exec|eval|system|passthru|shell_exec|popen|proc_open|pcntl_exec)\s*\(/i',
        '/\b(UNION\s+(ALL\s+)?SELECT|INSERT\s+INTO|DROP\s+TABLE|DELETE\s+FROM|UPDATE\s+\w+\s+SET)\b/i',
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/javascript\s*:/i',
        '/on\w+\s*=\s*["\'][^"\']*["\']/i',
        '/(\.\.\/|\.\.\\\\)/',
        '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
    ];

    private const MAX_LENGTHS = [
        'name' => 191,
        'description' => 65535,
        'command' => 1024,
        'email' => 191,
        'username' => 191,
        'startup' => 4096,
        'fqdn' => 253,
        'notes' => 256,
        'ip' => 45,
    ];

    public function __construct()
    {
    }

    /**
     * Sanitize a string value for safe storage.
     */
    public function sanitizeString(?string $value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = $this->removeNullBytes($value);
        $value = strip_tags($value);
        $value = $this->removeControlCharacters($value);

        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Sanitize an integer value.
     */
    public function sanitizeInteger(?int $value, int $min = 0, int $max = PHP_INT_MAX): ?int
    {
        if ($value === null) {
            return null;
        }

        return max($min, min($max, $value));
    }

    /**
     * Sanitize an email address.
     */
    public function sanitizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        $email = mb_strtolower(trim($email));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        return $email ?: null;
    }

    /**
     * Sanitize a server name.
     */
    public function sanitizeServerName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        $name = $this->sanitizeString($name, self::MAX_LENGTHS['name']);
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return $name;
    }

    /**
     * Sanitize an IP address or hostname.
     */
    public function sanitizeHost(?string $host): ?string
    {
        if (empty($host)) {
            return null;
        }

        $host = trim($host);
        $host = $this->removeControlCharacters($host);

        if (filter_var($host, FILTER_VALIDATE_IP) === false && filter_var($host, FILTER_VALIDATE_DOMAIN) === false) {
            return null;
        }

        return $host;
    }

    /**
     * Sanitize a port number.
     */
    public function sanitizePort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        return ($port >= 1 && $port <= 65535) ? $port : null;
    }

    /**
     * Sanitize a command string for server console.
     */
    public function sanitizeCommand(?string $command): ?string
    {
        if (empty($command)) {
            return null;
        }

        $command = trim($command);
        $command = $this->removeNullBytes($command);
        $command = $this->removeControlCharacters($command, true);

        if (mb_strlen($command) > self::MAX_LENGTHS['command']) {
            $command = mb_substr($command, 0, self::MAX_LENGTHS['command']);
        }

        return $command;
    }

    /**
     * Sanitize a URL.
     */
    public function sanitizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if ($url === false) {
            return null;
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return null;
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * Sanitize a FQDN (fully qualified domain name).
     */
    public function sanitizeFqdn(?string $fqdn): ?string
    {
        if (empty($fqdn)) {
            return null;
        }

        $fqdn = mb_strtolower(trim($fqdn, '.'));
        $fqdn = $this->removeControlCharacters($fqdn);

        if (mb_strlen($fqdn) > self::MAX_LENGTHS['fqdn']) {
            return null;
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/', $fqdn)) {
            return null;
        }

        return $fqdn;
    }

    /**
     * Scan input for potentially malicious patterns.
     */
    public function detectThreats(?string $input): array
    {
        if (empty($input)) {
            return [];
        }

        $threats = [];

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                $threats[] = $pattern;
            }
        }

        return $threats;
    }

    /**
     * Sanitize an entire array of data using field-specific rules.
     */
    public function sanitizeArray(array $data, array $rules = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (!is_string($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            $maxLength = $rules[$key] ?? null;
            $sanitized[$key] = $this->sanitizeString($value, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Remove null bytes from a string.
     */
    private function removeNullBytes(string $value): string
    {
        return str_replace("\0", '', $value);
    }

    /**
     * Remove control characters from a string.
     */
    private function removeControlCharacters(string $value, bool $keepNewlines = false): string
    {
        if ($keepNewlines) {
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        }

        return preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    }
}
