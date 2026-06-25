<?php

namespace Pterodactyl\Traits\DGEN;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

trait ChecksAddonAccess
{
    /**
     * Check if a user has access to a specific addon feature for a server.
     */
    protected function checkAddonAccess(User $user, Server $server, string $addon): bool
    {
        if ($user->root_admin) {
            return true;
        }

        if ($server->owner_id === $user->id) {
            return true;
        }

        $permission = "addon.{$addon}";

        return $user->can($permission, $server);
    }

    /**
     * Check if a server has a specific addon enabled.
     */
    protected function serverHasAddon(Server $server, string $addon): bool
    {
        $enabledAddons = config("dgen.addons.{$server->egg_id}", []);

        return in_array($addon, $enabledAddons);
    }

    /**
     * Get all available addons for a server's egg.
     */
    protected function getAvailableAddons(Server $server): array
    {
        $eggId = $server->egg_id;

        return config("dgen.addons.{$eggId}", []);
    }

    /**
     * Require addon access or throw an exception.
     *
     * @throws \Pterodactyl\Exceptions\Http\HttpForbiddenException
     */
    protected function requireAddonAccess(User $user, Server $server, string $addon): void
    {
        if (!$this->checkAddonAccess($user, $server, $addon)) {
            throw new \Pterodactyl\Exceptions\Http\HttpForbiddenException(
                "You do not have access to the {$addon} addon for this server."
            );
        }
    }

    /**
     * Check if the user can manage addon settings for a server.
     */
    protected function canManageAddon(User $user, Server $server, string $addon): bool
    {
        if ($user->root_admin) {
            return true;
        }

        if ($server->owner_id === $user->id) {
            return $user->can("addon.{$addon}.manage", $server);
        }

        return false;
    }
}
