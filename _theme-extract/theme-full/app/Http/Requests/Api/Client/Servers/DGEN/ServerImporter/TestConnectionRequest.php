<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\DGEN\ServerImporter;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class TestConnectionRequest extends ClientApiRequest
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
            'source_type' => ['required', 'string', Rule::in(['ftp', 'sftp', 'local', 's3'])],
            'source_host' => ['required_if:source_type,ftp,sftp', 'nullable', 'string', 'max:191'],
            'source_port' => ['required_if:source_type,ftp,sftp', 'nullable', 'integer', 'between:1,65535'],
            'source_username' => ['required_if:source_type,ftp,sftp', 'nullable', 'string', 'max:191'],
            'source_password' => ['required_if:source_type,ftp,sftp', 'nullable', 'string', 'max:191'],
        ];
    }
}
