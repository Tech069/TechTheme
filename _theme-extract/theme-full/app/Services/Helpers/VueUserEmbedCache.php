<?php

namespace Pterodactyl\Services\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\User;

class VueUserEmbedCache
{
    private const CACHE_PREFIX = 'vue_user_embed:';

    private const CACHE_TTL = 300;

    public function __construct()
    {
    }

    /**
     * Get cached user data for Vue embeds.
     */
    public function getUserData(User $user): array
    {
        $cacheKey = self::CACHE_PREFIX . $user->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
                'name_first' => $user->name_first,
                'name_last' => $user->name_last,
                'name' => $user->name,
                'root_admin' => $user->root_admin,
                'gravatar' => $user->gravatar,
                'language' => $user->language,
                'created_at' => $user->created_at?->toISOString(),
                'servers_count' => $user->servers()->count(),
                'identifier' => $user->identifier ?? $user->uuid,
            ];
        });
    }

    /**
     * Get cached user data by user ID.
     */
    public function getUserDataById(int $userId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $userId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            $user = User::find($userId);

            if (!$user) {
                return null;
            }

            return $this->getUserData($user);
        });
    }

    /**
     * Invalidate cached data for a user.
     */
    public function invalidate(User $user): void
    {
        Cache::forget(self::CACHE_PREFIX . $user->id);
    }

    /**
     * Invalidate cached data by user ID.
     */
    public function invalidateById(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    /**
     * Get multiple users' cached data at once.
     */
    public function getMultipleUsers(array $userIds): array
    {
        $results = [];

        foreach ($userIds as $userId) {
            $data = $this->getUserDataById($userId);
            if ($data) {
                $results[$userId] = $data;
            }
        }

        return $results;
    }

    /**
     * Build Vue-compatible user embed object.
     */
    public function buildEmbedObject(User $user): array
    {
        $data = $this->getUserData($user);

        return [
            'user' => $data,
            'meta' => [
                'avatar_url' => $user->gravatar
                    ? 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user->email))) . '?d=mp'
                    : null,
                'panel_url' => config('app.url'),
                'cached_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get cached embed with ETag support for conditional requests.
     */
    public function getEmbedWithETag(User $user): array
    {
        $cacheKey = self::CACHE_PREFIX . $user->id . ':etag';
        $data = $this->getUserData($user);

        $etag = md5(json_encode($data));
        $cachedEtag = Cache::get($cacheKey);

        if ($cachedEtag === $etag) {
            return [
                'data' => null,
                'etag' => $etag,
                'not_modified' => true,
            ];
        }

        Cache::put($cacheKey, $etag, self::CACHE_TTL);

        return [
            'data' => $this->buildEmbedObject($user),
            'etag' => $etag,
            'not_modified' => false,
        ];
    }

    /**
     * Clear all user embed caches.
     */
    public function flushAll(): void
    {
        // This is a best-effort approach since we can't use tags
        Log::info('Vue user embed cache flushed');
    }
}
