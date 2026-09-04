<?php

use App\Rules\BlockNoteDocument;
use Illuminate\Support\Facades\Validator;

/**
 * Build a block, optionally with nested children.
 *
 * @param  array<int, array<string, mixed>>  $children
 * @return array<string, mixed>
 */
function block(string $type = 'paragraph', array $children = []): array
{
    return [
        'id' => 'block-'.uniqid(),
        'type' => $type,
        'props' => ['textAlignment' => 'left'],
        'content' => [['type' => 'text', 'text' => 'Hi', 'styles' => []]],
        'children' => $children,
    ];
}

function documentPasses(mixed $document): bool
{
    return Validator::make(['content' => $document], ['content' => [new BlockNoteDocument]])->passes();
}

test('a nested document of well-formed blocks passes', function () {
    $document = [
        block('heading'),
        block('bulletListItem', [block('bulletListItem', [block('paragraph')])]),
        ['id' => 'table', 'type' => 'table', 'content' => null],
    ];

    expect(documentPasses($document))->toBeTrue();
});

test('a block without a string id or type fails', function (array $block) {
    expect(documentPasses([$block]))->toBeFalse();
})->with([
    'missing type' => [['id' => 'abc', 'children' => []]],
    'missing id' => [['type' => 'paragraph', 'children' => []]],
    'numeric id' => [['id' => 5, 'type' => 'paragraph']],
    'props not an array' => [['id' => 'abc', 'type' => 'paragraph', 'props' => 'bold']],
    'children not an array' => [['id' => 'abc', 'type' => 'paragraph', 'children' => 'nope']],
]);

test('a document that is not a list fails', function () {
    expect(documentPasses(['id' => 'abc', 'type' => 'paragraph']))->toBeFalse()
        ->and(documentPasses('text'))->toBeFalse();
});

test('a document nested deeper than the limit fails', function () {
    $document = block();

    for ($depth = 0; $depth <= BlockNoteDocument::MAX_DEPTH; $depth++) {
        $document = block('paragraph', [$document]);
    }

    expect(documentPasses([$document]))->toBeFalse();
});

test('a document larger than the size limit fails', function () {
    $huge = block();
    $huge['content'] = [['type' => 'text', 'text' => str_repeat('a', BlockNoteDocument::MAX_BYTES), 'styles' => []]];

    expect(documentPasses([$huge]))->toBeFalse();
});
