<?php

namespace Pterodactyl\Extensions\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class PaymenterProvider extends AbstractProvider
{
    /**
     * The OAuth provider name.
     */
    protected string $name = 'paymenter';

    /**
     * The API base URL.
     */
    protected string $apiBaseUrl;

    /**
     * Create a new provider instance.
     */
    public function __construct($config, string $redirectUrl)
    {
        parent::__construct($config, $redirectUrl);
        $this->apiBaseUrl = $config['api_base_url'] ?? 'https://api.paymenter.org';
    }

    /**
     * Get the authentication URL for the provider.
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase(
            $this->apiBaseUrl . '/oauth/authorize',
            $this->getCodeFields()
        );
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl(): string
    {
        return $this->apiBaseUrl . '/oauth/token';
    }

    /**
     * Get the user details URL for the provider.
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            "{$this->apiBaseUrl}/api/user",
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
            'name' => $user['name'] ?? $user['username'] ?? null,
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
     * Get the code fields for the OAuth redirect.
     */
    protected function getCodeFields(): array
    {
        return array_merge(parent::getCodeFields(), [
            'scope' => implode(' ', $this->scopes),
        ]);
    }
}
