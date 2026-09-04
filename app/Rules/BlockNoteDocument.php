<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a tree of editor blocks of a manageable size.
 *
 * Block types are not enumerated so custom blocks can be added to the
 * editor without touching the server; only the shape and size are checked.
 */
class BlockNoteDocument implements ValidationRule
{
    /**
     * The deepest nesting a document may have.
     */
    public const int MAX_DEPTH = 32;

    /**
     * The most blocks a document may hold.
     */
    public const int MAX_BLOCKS = 20000;

    /**
     * The largest JSON encoding a document may have, in bytes.
     */
    public const int MAX_BYTES = 2 * 1024 * 1024;

    protected int $blocks = 0;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $fail(__('The :attribute must be a list of blocks.'));

            return;
        }

        if (strlen((string) json_encode($value)) > self::MAX_BYTES) {
            $fail(__('The :attribute is too large.'));

            return;
        }

        $this->blocks = 0;

        if (! $this->validBlocks($value, 1)) {
            $fail(__('The :attribute contains an invalid block.'));
        }
    }

    /**
     * Determine if every entry is a well-formed block whose children are too.
     *
     * @param  array<mixed>  $blocks
     */
    protected function validBlocks(array $blocks, int $depth): bool
    {
        if ($depth > self::MAX_DEPTH) {
            return false;
        }

        foreach ($blocks as $block) {
            if (++$this->blocks > self::MAX_BLOCKS) {
                return false;
            }

            if (! is_array($block) || ! is_string($block['id'] ?? null) || ! is_string($block['type'] ?? null)) {
                return false;
            }

            if (array_key_exists('props', $block) && ! is_array($block['props'])) {
                return false;
            }

            if (array_key_exists('content', $block) && ! is_array($block['content']) && ! is_string($block['content']) && $block['content'] !== null) {
                return false;
            }

            $children = $block['children'] ?? [];

            if (! is_array($children) || ! $this->validBlocks($children, $depth + 1)) {
                return false;
            }
        }

        return true;
    }
}
