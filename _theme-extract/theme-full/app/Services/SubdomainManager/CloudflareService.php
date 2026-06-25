<?php

namespace Pterodactyl\Services\SubdomainManager;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CloudflareService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    private string $apiKey;
    private string $email;
    private string $zoneId;

    public function __construct()
    {
        $this->apiKey = config('services.cloudflare.api_key', '');
        $this->email = config('services.cloudflare.email', '');
        $this->zoneId = config('services.cloudflare.zone_id', '');
    }

    /**
     * Make a request to the Cloudflare API.
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        if (empty($this->apiKey) || empty($this->zoneId)) {
            return ['success' => false, 'errors' => [['message' => 'Cloudflare API credentials not configured']]];
        }

        try {
            $url = self::API_BASE . '/zones/' . $this->zoneId . $endpoint;

            $response = Http::withHeaders([
                'X-Auth-Email' => $this->email,
                'X-Auth-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15);

            $response = match ($method) {
                'GET' => $response->get($url, $data),
                'POST' => $response->post($url, $data),
                'PUT' => $response->put($url, $data),
                'DELETE' => $response->delete($url),
                'PATCH' => $response->patch($url, $data),
                default => $response->get($url),
            };

            return $response->json();
        } catch (\Exception $exception) {
            Log::error('Cloudflare API error', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'errors' => [['message' => $exception->getMessage()]]];
        }
    }

    /**
     * Create a DNS record.
     */
    public function createDnsRecord(string $type, string $name, string $content, int $ttl = 1, bool $proxied = false): array
    {
        $result = $this->request('POST', '/dns_records', [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied,
        ]);

        if ($result['success'] ?? false) {
            Log::info('Cloudflare DNS record created', [
                'type' => $type,
                'name' => $name,
                'content' => $content,
            ]);
        }

        return $result;
    }

    /**
     * Update a DNS record.
     */
    public function updateDnsRecord(string $recordId, array $data): array
    {
        $result = $this->request('PUT', '/dns_records/' . $recordId, $data);

        if ($result['success'] ?? false) {
            Log::info('Cloudflare DNS record updated', [
                'record_id' => $recordId,
            ]);
        }

        return $result;
    }

    /**
     * Delete a DNS record.
     */
    public function deleteDnsRecord(string $recordId): array
    {
        $result = $this->request('DELETE', '/dns_records/' . $recordId);

        if ($result['success'] ?? false) {
            Log::info('Cloudflare DNS record deleted', [
                'record_id' => $recordId,
            ]);
        }

        return $result;
    }

    /**
     * List DNS records with optional filters.
     */
    public function listDnsRecords(string $type = '', string $name = '', int $page = 1, int $perPage = 100): array
    {
        $params = array_filter([
            'type' => $type,
            'name' => $name,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->request('GET', '/dns_records', $params);
    }

    /**
     * Get a specific DNS record.
     */
    public function getDnsRecord(string $recordId): array
    {
        return $this->request('GET', '/dns_records/' . $recordId);
    }

    /**
     * Create an A record for a subdomain.
     */
    public function createARecord(string $subdomain, string $ipAddress, bool $proxied = false): array
    {
        $zoneName = config('services.cloudflare.zone_name', '');
        $fqdn = $subdomain . '.' . $zoneName;

        return $this->createDnsRecord('A', $fqdn, $ipAddress, 1, $proxied);
    }

    /**
     * Create a CNAME record for a subdomain.
     */
    public function createCnameRecord(string $subdomain, string $target, bool $proxied = false): array
    {
        $zoneName = config('services.cloudflare.zone_name', '');
        $fqdn = $subdomain . '.' . $zoneName;

        return $this->createDnsRecord('CNAME', $fqdn, $target, 1, $proxied);
    }

    /**
     * Create an SRV record.
     */
    public function createSrvRecord(string $service, string $proto, string $name, int $priority, int $weight, int $port, string $target): array
    {
        $zoneName = config('services.cloudflare.zone_name', '');
        $fqdn = $service . '.' . $proto . '.' . $name . '.' . $zoneName;

        return $this->createDnsRecord('SRV', $fqdn, "$priority $weight $port $target", 1);
    }

    /**
     * Delete a DNS record by name and type.
     */
    public function deleteByNameAndType(string $name, string $type): array
    {
        $records = $this->listDnsRecords($type, $name);

        if (!($records['success'] ?? false)) {
            return $records;
        }

        $results = [];
        foreach (($records['result'] ?? []) as $record) {
            $results[] = $this->deleteDnsRecord($record['id']);
        }

        return [
            'success' => true,
            'deleted' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Purge Cloudflare cache for a subdomain.
     */
    public function purgeCache(array $files = []): array
    {
        $payload = !empty($files)
            ? ['files' => $files]
            : ['purge_everything' => true];

        return $this->request('DELETE', '/purge_cache', $payload);
    }
}
