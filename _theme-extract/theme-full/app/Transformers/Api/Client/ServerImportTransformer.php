<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\ServerImport;

class ServerImportTransformer extends BaseClientTransformer
{
    /**
     * Returns the resource name for the API output.
     */
    public function getResourceName(): string
    {
        return ServerImport::RESOURCE_NAME;
    }

    /**
     * Transform the ServerImport model into an API representation.
     */
    public function transform(ServerImport $import): array
    {
        return [
            'id' => $import->id,
            'user_id' => $import->user_id,
            'server_id' => $import->server_id,
            'source_type' => $import->source_type,
            'status' => $import->status,
            'created_at' => $this->formatTimestamp($import->created_at),
            'updated_at' => $this->formatTimestamp($import->updated_at),
        ];
    }

    /**
     * Include the server relation.
     */
    public function includeServer(ServerImport $import): \League\Fractal\Resource\Item|null
    {
        if (!$import->relationLoaded('server') && !$import->server) {
            return null;
        }

        return $this->item($import->server, $this->makeTransformer(ServerTransformer::class));
    }

    /**
     * Include the user relation.
     */
    public function includeUser(ServerImport $import): \League\Fractal\Resource\Item|null
    {
        if (!$import->relationLoaded('user') && !$import->user) {
            return null;
        }

        return $this->item($import->user, $this->makeTransformer(UserTransformer::class));
    }
}
