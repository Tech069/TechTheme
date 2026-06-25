<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\DGEN\MinecraftVotifierTester;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class TestMinecraftVotifierRequest extends ClientApiRequest
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
            'votifier_host' => ['required', 'string', 'max:191'],
            'votifier_port' => ['required', 'integer', 'between:1,65535'],
            'votifier_token' => ['required', 'string', 'max:191'],
            'test_username' => ['required', 'string', 'between:1,16'],
        ];
    }
}
