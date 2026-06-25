<?php

namespace Pterodactyl\Extensions\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class DiscordProvider extends AbstractProvider
{
    /**
     * The OAuth provider name.
     */
    protected string $name = 'discord';

    /**
     * The Discord API base URL.
     */
    protected string $apiBaseUrl = 'https://discord.com/api/v10';

    /**
     * Get the authentication URL for the provider.
     */
    protected function getAuthUrl(): string
    {
        return $this->buildAuthUrlFromBase(
            'https://discord.com/api/oauth2/authorize',
            $this->getCodeFields()
        );
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl(): string
    {
        return 'https://discord.com/api/oauth2/token';
    }

    /**
     * Get the user details URL for the provider.
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            "{$this->apiBaseUrl}/users/@me",
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
        $avatar = $user['avatar'] ?? null;
        $userId = $user['id'] ?? null;

        $avatarUrl = null;
        if ($avatar && $userId) {
            $ext = str_starts_with($avatar, 'a_') ? 'gif' : 'png';
            $avatarUrl = "https://cdn.discordapp.com/avatars/{$userId}/{$avatar}.{$ext}";
        }

        return (new SocialiteUser)->setRaw($user)->map([
            'id' => $userId,
            'nickname' => $user['username'] ?? null,
            'name' => $user['global_name'] ?? $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $avatarUrl,
            'avatar_original' => $avatarUrl,
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
            'prompt' => $this->prompt ?? 'consent',
        ]);
    }
}
