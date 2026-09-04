<?php

namespace App\Integrations\Data;

/**
 * An app teams may connect through the integration provider.
 */
readonly class ConnectableApp
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $logo,
        public ?string $authScheme,
    ) {
        //
    }
}
