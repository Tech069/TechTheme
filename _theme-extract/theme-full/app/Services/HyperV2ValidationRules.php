<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Factory as ValidationFactory;

class HyperV2ValidationRules
{
    public function __construct(
        private ValidationFactory $validationFactory,
    ) {
    }

    /**
     * Get custom validation rules for server creation/update.
     */
    public function getServerRules(): array
    {
        return [
            'name' => 'required|string|min:1|max:191',
            'description' => 'nullable|string|max:65535',
            'memory' => 'required|integer|min:128|max:262144',
            'swap' => 'required|integer|min:-1|max:262144',
            'disk' => 'required|integer|min:100|max:10485760',
            'cpu' => 'required|integer|min:0|max:100000',
            'io' => 'required|integer|between:10,1000',
            'threads' => 'nullable|regex:/^[0-9\-,]+$/',
            'node_id' => 'required|integer|exists:nodes,id',
            'egg_id' => 'required|integer|exists:eggs,id',
            'allocation_id' => 'required|integer|exists:allocations,id',
            'startup' => 'required|string|max:4096',
            'image' => 'required|string|max:191',
            'oom_disabled' => 'sometimes|boolean',
            'skip_scripts' => 'sometimes|boolean',
            'database_limit' => 'present|nullable|integer|min:0|max:100',
            'allocation_limit' => 'sometimes|nullable|integer|min:0|max:100',
            'backup_limit' => 'present|nullable|integer|min:0|max:50',
        ];
    }

    /**
     * Get custom validation rules for user management.
     */
    public function getUserRules(): array
    {
        return [
            'username' => 'required|string|between:1,191|alpha_dash',
            'email' => 'required|email:strict|between:1,191',
            'name_first' => 'required|string|between:1,191',
            'name_last' => 'required|string|between:1,191',
            'password' => 'sometimes|nullable|string|min:8|max:255',
            'root_admin' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom validation rules for node management.
     */
    public function getNodeRules(): array
    {
        return [
            'name' => 'required|regex:/^([\w .-]{1,100})$/',
            'description' => 'nullable|string|max:500',
            'location_id' => 'required|integer|exists:locations,id',
            'fqdn' => 'required|string|max:253',
            'scheme' => 'required|in:http,https',
            'behind_proxy' => 'sometimes|boolean',
            'memory' => 'required|integer|min:1',
            'memory_overallocate' => 'required|integer|min:-1',
            'disk' => 'required|integer|min:1',
            'disk_overallocate' => 'required|integer|min:-1',
            'daemonListen' => 'required|integer|between:1,65535',
            'daemonSFTP' => 'required|integer|between:1,65535',
            'daemonBase' => 'sometimes|required|regex:/^([\/][\d\w.\-\/]+)$/',
            'maintenance_mode' => 'sometimes|boolean',
            'upload_size' => 'integer|min:1|max:1024',
        ];
    }

    /**
     * Get custom validation rules for allocation management.
     */
    public function getAllocationRules(): array
    {
        return [
            'ip' => 'required|ip',
            'port' => 'required|integer|between:1024,65535',
            'node_id' => 'required|integer|exists:nodes,id',
            'notes' => 'nullable|string|max:256',
        ];
    }

    /**
     * Get custom validation rules for database management.
     */
    public function getDatabaseRules(): array
    {
        return [
            'database_host_id' => 'required|integer|exists:database_hosts,id',
            'database' => 'required|string|alpha_dash|between:3,48',
            'remote' => 'required|string|regex:/^[\w\-\/.%:]+$/',
            'max_connections' => 'nullable|integer|min:1|max:150',
        ];
    }

    /**
     * Get validation rules for addon configuration.
     */
    public function getAddonConfigRules(string $addon): array
    {
        $baseRules = [];

        switch ($addon) {
            case 'discord_integration':
                $baseRules = [
                    'webhook_url' => 'nullable|url',
                    'server_status_channel_id' => 'nullable|string',
                    'player_count_channel_id' => 'nullable|string',
                    'notify_on_start' => 'boolean',
                    'notify_on_stop' => 'boolean',
                    'notify_on_crash' => 'boolean',
                    'embed_color' => 'integer|min:0|max:16777215',
                ];
                break;

            case 'auto_backup':
                $baseRules = [
                    'interval_hours' => 'required|integer|min:1|max:168',
                    'max_backups' => 'required|integer|min:1|max:50',
                    'include_databases' => 'boolean',
                ];
                break;

            case 'rcon':
                $baseRules = [
                    'host' => 'required|string',
                    'port' => 'required|integer|between:1,65535',
                    'password' => 'required|string',
                    'type' => 'required|in:minecraft,source,fivem,ark,mumble',
                ];
                break;
        }

        return $baseRules;
    }

    /**
     * Validate data against custom rules.
     */
    public function validate(array $data, array $rules): array
    {
        $validator = $this->validationFactory->make($data, $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }
}
