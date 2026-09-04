<?php

namespace App\Data;

use Illuminate\Support\Arr;

/**
 * A connected account in Composio: an authorized connection to an app.
 */
readonly class ComposioConnectedAccount
{
    public function __construct(
        public string $id,
        public string $toolkitSlug,
        public ?string $authConfigId,
        public string $status,
        public ?string $statusReason,
    ) {
        //
    }

    /**
     * Build the connected account from a Composio API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $authConfigId = Arr::get($data, 'auth_config.id');
        $statusReason = Arr::get($data, 'status_reason');

        return new self(
            id: (string) Arr::get($data, 'id', ''),
            toolkitSlug: (string) Arr::get($data, 'toolkit.slug', ''),
            authConfigId: is_string($authConfigId) && $authConfigId !== '' ? $authConfigId : null,
            status: (string) Arr::get($data, 'status', ''),
            statusReason: is_string($statusReason) && $statusReason !== '' ? $statusReason : null,
        );
    }
}
