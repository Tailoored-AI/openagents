<?php

namespace App\Data;

/**
 * A hosted authorization link created by Composio for a pending connection.
 */
readonly class ComposioConnectionLink
{
    public function __construct(
        public string $connectedAccountId,
        public string $redirectUrl,
    ) {
        //
    }
}
