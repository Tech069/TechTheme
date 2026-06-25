<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\ServerRecycleBin;

class RecycleBinFileTransformer extends BaseClientTransformer
{
    /**
     * Returns the resource name for the API output.
     */
    public function getResourceName(): string
    {
        return 'recycle_bin_file';
    }

    /**
     * Transform the ServerRecycleBin model into an API representation.
     */
    public function transform(ServerRecycleBin $entry): array
    {
        $data = $entry->data ?? [];

        return [
            'id' => $entry->id,
            'server_id' => $entry->server_id,
            'user_id' => $entry->user_id,
            'data' => [
                'folder_path' => $data['folder_path'] ?? null,
                'folder_name' => $data['folder_name'] ?? null,
                'folder_size_bytes' => $data['folder_size_bytes'] ?? 0,
                'folder_size_human' => $data['folder_size_human'] ?? '0 B',
                'file_count' => $data['file_count'] ?? 0,
                'deleted_files' => $data['deleted_files'] ?? [],
            ],
            'deleted_at' => $entry->deleted_at?->toIso8601String(),
            'restore_until' => $entry->restore_until?->toIso8601String(),
            'created_at' => $entry->created_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }
}
