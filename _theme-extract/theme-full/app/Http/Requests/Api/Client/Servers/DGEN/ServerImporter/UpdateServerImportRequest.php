<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\DGEN\ServerImporter;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateServerImportRequest extends ClientApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $server = $this->route()->parameter('server');

        if (!$server) {
            return false;
        }

        return $this->user()->can('settings.update', $server);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'import_id' => ['required', 'integer', 'exists:server_imports,id'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'in_progress', 'completed', 'failed'])],
            'source_host' => ['sometimes', 'nullable', 'string', 'max:191'],
            'source_port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'source_username' => ['sometimes', 'nullable', 'string', 'max:191'],
            'source_password' => ['sometimes', 'nullable', 'string', 'max:191'],
            'source_path' => ['sometimes', 'string', 'max:500'],
        ];
    }
}
