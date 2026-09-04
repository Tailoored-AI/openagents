<?php

namespace App\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * A toolkit in Composio: the app behind one or more auth configs.
 */
readonly class ComposioToolkit
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $logo,
    ) {
        //
    }

    /**
     * Build the toolkit from a Composio API payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $slug = (string) Arr::get($data, 'slug', '');
        $description = Arr::get($data, 'meta.description');
        $logo = Arr::get($data, 'meta.logo');

        return new self(
            slug: $slug,
            name: (string) (Arr::get($data, 'name') ?: Str::headline($slug)),
            description: is_string($description) && $description !== '' ? $description : null,
            logo: is_string($logo) && $logo !== '' ? $logo : null,
        );
    }

    /**
     * Build a placeholder for a toolkit Composio could not describe.
     */
    public static function fallback(string $slug): self
    {
        return new self(
            slug: $slug,
            name: Str::headline($slug),
            description: null,
            logo: null,
        );
    }
}
