<?php

namespace App\Integrations\Data;

/**
 * The outcome of executing a tool through the integration provider.
 */
readonly class ToolResult
{
    public function __construct(
        public bool $successful,
        public mixed $data,
        public ?string $error,
        public ?string $reference,
    ) {
        //
    }
}
