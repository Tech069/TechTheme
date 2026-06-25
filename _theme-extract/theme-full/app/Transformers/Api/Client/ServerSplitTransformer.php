<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\ServerSplit;

class ServerSplitTransformer extends BaseClientTransformer
{
    /**
     * Returns the resource name for the API output.
     */
    public function getResourceName(): string
    {
        return 'server_split';
    }

    /**
     * Transform the ServerSplit model into an API representation.
     */
    public function transform(ServerSplit $split): array
    {
        return [
            'id' => $split->id,
            'parent_server_id' => $split->parent_server_id,
            'child_server_id' => $split->child_server_id,
            'split_type' => $split->split_type,
            'status' => $split->status,
            'created_at' => $this->formatTimestamp($split->created_at),
            'updated_at' => $this->formatTimestamp($split->updated_at),
        ];
    }

    /**
     * Include the parent server relation.
     */
    public function includeParentServer(ServerSplit $split): \League\Fractal\Resource\Item|null
    {
        if (!$split->relationLoaded('parentServer') && !$split->parentServer) {
            return null;
        }

        return $this->item($split->parentServer, $this->makeTransformer(ServerTransformer::class));
    }

    /**
     * Include the child server relation.
     */
    public function includeChildServer(ServerSplit $split): \League\Fractal\Resource\Item|null
    {
        if (!$split->relationLoaded('childServer') && !$split->childServer) {
            return null;
        }

        return $this->item($split->childServer, $this->makeTransformer(ServerTransformer::class));
    }
}
