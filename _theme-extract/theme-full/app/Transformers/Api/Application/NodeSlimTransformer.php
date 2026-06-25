<?php

namespace Pterodactyl\Transformers\Api\Application;

use Pterodactyl\Models\Node;

class NodeSlimTransformer extends BaseTransformer
{
    /**
     * Returns the resource name for the API output.
     */
    public function getResourceName(): string
    {
        return Node::RESOURCE_NAME;
    }

    /**
     * Transform the Node model into a slim API representation.
     */
    public function transform(Node $node): array
    {
        return [
            'id' => $node->id,
            'uuid' => $node->uuid,
            'name' => $node->name,
            'description' => $node->description,
            'location_id' => $node->location_id,
            'fqdn' => $node->fqdn,
            'scheme' => $node->scheme,
            'behind_proxy' => $node->behind_proxy,
            'memory' => $node->memory,
            'memory_overallocate' => $node->memory_overallocate,
            'disk' => $node->disk,
            'disk_overallocate' => $node->disk_overallocate,
            'daemon_listen' => $node->daemonListen,
            'daemon_sftp' => $node->daemonSFTP,
            'maintenance_mode' => $node->maintenance_mode,
            'public' => $node->public,
            'created_at' => $this->formatTimestamp($node->created_at),
            'updated_at' => $this->formatTimestamp($node->updated_at),
        ];
    }
}
