<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\DGEN\ServerSplitter;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreServerSplitRequest extends ClientApiRequest
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
            'split_type' => ['required', 'string', Rule::in(['files', 'database', 'full'])],
            'target_node_id' => ['required', 'integer', 'exists:nodes,id'],
            'target_egg_id' => ['required', 'integer', 'exists:eggs,id'],
            'split_config' => ['sometimes', 'array'],
            'split_config.files' => ['sometimes', 'array'],
            'split_config.files.*' => ['string'],
            'split_config.databases' => ['sometimes', 'array'],
            'split_config.databases.*' => ['string'],
            'split_config.variables' => ['sometimes', 'array'],
            'split_config.variables.*' => ['string'],
            'name' => ['required', 'string', 'between:1,191'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
