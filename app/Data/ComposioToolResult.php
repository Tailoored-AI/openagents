<?php

namespace App\Data;

use Illuminate\Support\Arr;

/**
 * The outcome of executing a Composio tool.
 */
readonly class ComposioToolResult
{
    public function __construct(
        public bool $successful,
        public mixed $data,
        public ?string $error,
        public ?string $logId,
    ) {
        //
    }

    /**
     * Build the result from a Composio API payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $error = Arr::get($payload, 'error');
        $logId = Arr::get($payload, 'log_id');

        return new self(
            successful: (bool) Arr::get($payload, 'successful', false),
            data: Arr::get($payload, 'data'),
            error: is_string($error) && $error !== '' ? $error : null,
            logId: is_string($logId) && $logId !== '' ? $logId : null,
        );
    }
}
