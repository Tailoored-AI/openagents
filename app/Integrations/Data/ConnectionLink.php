<?php

namespace App\Integrations\Data;

/**
 * A pending connection and the URL the user must visit to authorize it.
 */
readonly class ConnectionLink
{
    public function __construct(
        public string $connectionId,
        public string $redirectUrl,
    ) {
        //
    }
}
