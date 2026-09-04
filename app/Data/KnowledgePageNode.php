<?php

namespace App\Data;

use App\Models\KnowledgePage;
use Illuminate\Support\Collection;

/**
 * A page in the knowledge directory tree, with the pages nested under it.
 */
readonly class KnowledgePageNode
{
    /**
     * @param  list<self>  $children
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public string $updatedAt,
        public array $children,
    ) {
        //
    }

    /**
     * Arrange a flat, ordered list of pages into a tree of nodes.
     *
     * @param  Collection<int, KnowledgePage>  $pages
     * @return list<self>
     */
    public static function tree(Collection $pages): array
    {
        $byParent = $pages->groupBy(fn (KnowledgePage $page) => $page->parent_id ?? 0);

        return self::branch($byParent, 0);
    }

    /**
     * @param  Collection<int|string, Collection<int, KnowledgePage>>  $byParent
     * @return list<self>
     */
    protected static function branch(Collection $byParent, int $parentId): array
    {
        $nodes = $byParent->get($parentId, collect())
            ->map(fn (KnowledgePage $page) => new self(
                id: $page->id,
                title: $page->title,
                updatedAt: (string) $page->updated_at?->toISOString(),
                children: self::branch($byParent, $page->id),
            ));

        return array_values($nodes->all());
    }
}
