<?php

namespace Pterodactyl\Exceptions\Service\Subuser;

use Pterodactyl\Exceptions\DisplayException;

class UserNotFoundForSubuserException extends DisplayException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $identifier = '')
    {
        $message = 'The user you are trying to add as a subuser could not be found';

        if ($identifier) {
            $message .= " (searched for: {$identifier})";
        }

        $message .= '. Please verify the email or username and try again.';

        parent::__construct($message);
    }
}
