<?php

namespace Pterodactyl\Services\ReverseProxy;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SslService
{
    private const LETS_ENCRYPT_API = 'https://acme-v02.api.letsencrypt.org/directory';
    private const LETS_ENCRYPT_STAGING = 'https://acme-staging-v02.api.letsencrypt.org/directory';

    private const CERT_BASE_PATH = '/etc/letsencrypt/live';
    private const CERT_DIR = '/etc/letsencrypt';

    public function __construct()
    {
    }

    /**
     * Request an SSL certificate via Let's Encrypt.
     */
    public function requestCertificate(string $domain, bool $staging = false): array
    {
        $baseDir = self::CERT_BASE_PATH . '/' . strtolower($domain);
        $certPath = $baseDir . '/fullchain.pem';
        $keyPath = $baseDir . '/privkey.pem';

        // Check if a valid certificate already exists
        if ($this->certificateExists($domain) && !$this->isCertificateExpiring($domain)) {
            return [
                'cert_path' => $certPath,
                'key_path' => $keyPath,
                'existing' => true,
            ];
        }

        try {
            // Use certbot for certificate generation
            $certbotCmd = sprintf(
                'certbot certnon-interactive --agree-tos --email %s -d %s %s 2>&1',
                config('ssl.admin_email', 'admin@example.com'),
                $domain,
                $staging ? '--staging' : ''
            );

            $output = [];
            $returnCode = 0;
            exec($certbotCmd, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('Certbot certificate request failed', [
                    'domain' => $domain,
                    'output' => implode("\n", $output),
                ]);

                return ['cert_path' => null, 'key_path' => null, 'error' => implode("\n", $output)];
            }

            return [
                'cert_path' => $certPath,
                'key_path' => $keyPath,
                'existing' => false,
            ];
        } catch (\Exception $exception) {
            Log::error('SSL certificate request failed', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ]);

            return ['cert_path' => null, 'key_path' => null, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Check if a certificate exists for a domain.
     */
    public function certificateExists(string $domain): bool
    {
        $baseDir = self::CERT_BASE_PATH . '/' . strtolower($domain);

        return File::exists($baseDir . '/fullchain.pem') && File::exists($baseDir . '/privkey.pem');
    }

    /**
     * Check if a certificate is expiring soon.
     */
    public function isCertificateExpiring(string $domain, int $daysThreshold = 30): bool
    {
        $certPath = self::CERT_BASE_PATH . '/' . strtolower($domain) . '/fullchain.pem';

        if (!File::exists($certPath)) {
            return true;
        }

        try {
            $certContent = File::get($certPath);
            $certData = openssl_x509_parse($certContent);

            if (!$certData || !isset($certData['valid_to_time'])) {
                return true;
            }

            $expiryTime = $certData['valid_to_time'];
            $thresholdTime = strtotime("+{$daysThreshold} days");

            return $expiryTime < $thresholdTime;
        } catch (\Exception $exception) {
            return true;
        }
    }

    /**
     * Renew a certificate for a domain.
     */
    public function renewCertificate(string $domain): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            exec("certbot renew --cert-name $domain 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('Certificate renewal failed', [
                    'domain' => $domain,
                    'output' => implode("\n", $output),
                ]);

                return false;
            }

            // Reload nginx to use the new certificate
            exec('systemctl reload nginx 2>&1');

            return true;
        } catch (\Exception $exception) {
            Log::error('Certificate renewal error', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get certificate information for a domain.
     */
    public function getCertificateInfo(string $domain): ?array
    {
        $certPath = self::CERT_BASE_PATH . '/' . strtolower($domain) . '/fullchain.pem';

        if (!File::exists($certPath)) {
            return null;
        }

        try {
            $certContent = File::get($certPath);
            $certData = openssl_x509_parse($certContent);

            if (!$certData) {
                return null;
            }

            return [
                'domain' => $domain,
                'subject' => $certData['subject']['CN'] ?? $domain,
                'issuer' => $certData['issuer']['CN'] ?? 'Unknown',
                'valid_from' => date('Y-m-d H:i:s', $certData['valid_from_time']),
                'valid_to' => date('Y-m-d H:i:s', $certData['valid_to_time']),
                'serial' => $certData['serial_number_hex'] ?? '',
                'fingerprint' => openssl_x509_fingerprint($certContent),
                'expiring_soon' => $this->isCertificateExpiring($domain),
            ];
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * List all managed certificates.
     */
    public function listCertificates(): array
    {
        if (!File::isDirectory(self::CERT_BASE_PATH)) {
            return [];
        }

        $directories = File::directories(self::CERT_BASE_PATH);
        $certificates = [];

        foreach ($directories as $directory) {
            $domain = basename($directory);
            $info = $this->getCertificateInfo($domain);
            if ($info) {
                $certificates[] = $info;
            }
        }

        return $certificates;
    }

    /**
     * Delete a certificate for a domain.
     */
    public function deleteCertificate(string $domain): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            exec("certbot delete --cert-name $domain 2>&1", $output, $returnCode);

            return $returnCode === 0;
        } catch (\Exception $exception) {
            return false;
        }
    }
}
