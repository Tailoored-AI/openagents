<?php

namespace App\Integrations\Data;

use App\Enums\IntegrationStatus;

/**
 * The state of a team's connection to an app, as reported by the integration provider.
 */
readonly class Connection
{
    public function __construct(
        public string $id,
        public string $appSlug,
        public IntegrationStatus $status,
        public ?string $statusReason,
    ) {
        //
    }
}
