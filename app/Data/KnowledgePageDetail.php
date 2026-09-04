<?php

namespace App\Data;

use App\Models\KnowledgePage;

/**
 * A page as opened in the editor.
 */
readonly class KnowledgePageDetail
{
    /**
     * @param  array<int, array<string, mixed>>|null  $content
     * @param  array{id: int, name: string}|null  $createdBy
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public ?array $content,
        public int $version,
        public string $updatedAt,
        public ?int $parentId,
        public ?array $createdBy,
        public ?string $updatedBy,
        public bool $canDelete,
        public int $descendantCount,
    ) {
        //
    }

    public static function fromPage(KnowledgePage $page, bool $canDelete): self
    {
        return new self(
            id: $page->id,
            title: $page->title,
            content: $page->content,
            version: $page->version,
            updatedAt: (string) $page->updated_at?->toISOString(),
            parentId: $page->parent_id,
            createdBy: $page->author ? ['id' => $page->author->id, 'name' => $page->author->name] : null,
            updatedBy: $page->editor?->name,
            canDelete: $canDelete,
            descendantCount: $page->descendantCount(),
        );
    }
}
