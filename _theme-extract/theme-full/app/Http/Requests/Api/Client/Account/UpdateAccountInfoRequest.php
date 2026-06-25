<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account;

use Illuminate\Validation\Rule;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateAccountInfoRequest extends ClientApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => [
                'sometimes',
                'string',
                'between:1,191',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],
            'email' => [
                'sometimes',
                'email:strict',
                'between:1,191',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'name_first' => ['sometimes', 'string', 'between:1,191'],
            'name_last' => ['sometimes', 'string', 'between:1,191'],
            'language' => ['sometimes', 'string'],
            'gravatar' => ['sometimes', 'boolean'],
        ];
    }
}
