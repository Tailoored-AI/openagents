<?php

namespace App\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * An auth config in the Composio project: an app that teams may connect.
 */
readonly class ComposioAuthConfig
{
    public function __construct(
        public string $id,
        public string $name,
        public string $toolkitSlug,
        public ?string $toolkitLogo,
        public string $authScheme,
        public bool $isComposioManaged,
    ) {
        //
    }

    /**
     * Build the auth config from a Composio API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $toolkitSlug = (string) Arr::get($data, 'toolkit.slug', '');
        $logo = Arr::get($data, 'toolkit.logo');

        return new self(
            id: (string) Arr::get($data, 'id', ''),
            name: (string) (Arr::get($data, 'name') ?: Str::headline($toolkitSlug)),
            toolkitSlug: $toolkitSlug,
            toolkitLogo: is_string($logo) && $logo !== '' ? $logo : null,
            authScheme: (string) Arr::get($data, 'auth_scheme', ''),
            isComposioManaged: (bool) Arr::get($data, 'is_composio_managed', false),
        );
    }
}
