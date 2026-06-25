<?php

namespace Pterodactyl\Http\Requests\Auth;

use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
                'required',
                'string',
                'between:1,191',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'email:strict',
                'between:1,191',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
            'name_first' => ['required', 'string', 'between:1,191'],
            'name_last' => ['required', 'string', 'between:1,191'],
            'g-recaptcha-response' => [
                'required_if:recaptcha_key,NULL',
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'This email address is already registered.',
            'email.email' => 'Please enter a valid email address.',
            'password.confirmed' => 'The password confirmation does not match.',
            'g-recaptcha-response.required_if' => 'Please complete the CAPTCHA verification.',
        ];
    }
}
