<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;

class RegisterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $fields = [
                ['name' => 'username', 'type' => 'text', 'required' => true, 'label' => 'Username'],
                ['name' => 'email', 'type' => 'email', 'required' => true, 'label' => 'Email'],
                ['name' => 'password', 'type' => 'password', 'required' => true, 'label' => 'Password'],
                ['name' => 'password_confirmation', 'type' => 'password', 'required' => true, 'label' => 'Confirm Password'],
            ];

            if (config('dgen.registration.name_fields', true)) {
                array_splice($fields, 1, 0, [
                    ['name' => 'name_first', 'type' => 'text', 'required' => false, 'label' => 'First Name'],
                    ['name' => 'name_last', 'type' => 'text', 'required' => false, 'label' => 'Last Name'],
                ]);
            }

            return response()->json([
                'fields' => $fields,
                'recaptcha_enabled' => config('dgen.recaptcha.enabled', false),
                'terms_url' => config('dgen.registration.terms_url'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|min:3|max:191|unique:users',
            'email' => 'required|email|max:191|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'name_first' => 'nullable|string|max:191',
            'name_last' => 'nullable|string|max:191',
        ]);

        try {
            $user = User::create([
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'name_first' => $request->input('name_first'),
                'name_last' => $request->input('name_last'),
                'uuid' => Str::uuid(),
                'language' => 'en',
                'root_admin' => false,
                'use_totp' => false,
                'gravatar' => true,
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                ],
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }
}
