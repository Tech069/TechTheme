<?php

namespace Pterodactyl\Extensions\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class DgenProvider extends AbstractProvider
{
    /**
     * The OAuth provider name.
     */
    protected string $name = 'dgen';

    /**
     * The API base URL.
     */
    protected string $apiBaseUrl = 'https://api.dgen.gg';

    /**
     * Get the authentication URL for the provider.
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase(
            'https://auth.dgen.gg/oauth/authorize',
            $this->getCodeFields()
        );
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl(): string
    {
        return 'https://auth.dgen.gg/oauth/token';
    }

    /**
     * Get the user details URL for the provider.
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            "{$this->apiBaseUrl}/user",
            [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    /**
     * Map the raw user response to a Socialite User object.
     */
    protected function mapUserToObject(array $user): SocialiteUser
    {
        return (new SocialiteUser)->setRaw($user)->map([
            'id' => $user['id'] ?? null,
            'nickname' => $user['username'] ?? null,
            'name' => $user['display_name'] ?? $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['avatar_url'] ?? null,
            'avatar_original' => $user['avatar_url'] ?? null,
        ]);
    }

    /**
     * Get the default scopes for the provider.
     */
    protected function getDefaultScopes(): array
    {
        return ['identify', 'email'];
    }

    /**
     * Get the fields to retrieve from the user.
     */
    protected function getCodeFields(): array
    {
        return array_merge(parent::getCodeFields(), [
            'scope' => implode(' ', $this->scopes),
        ]);
    }
}
