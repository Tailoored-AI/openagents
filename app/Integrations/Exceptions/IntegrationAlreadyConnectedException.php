<?php

namespace App\Integrations\Exceptions;

use App\Models\Integration;
use RuntimeException;

/**
 * Thrown when a team tries to connect an app it already has a working connection to.
 */
class IntegrationAlreadyConnectedException extends RuntimeException
{
    public function __construct(public readonly Integration $integration)
    {
        parent::__construct("Integration [{$integration->id}] is already connected.");
    }
}
