<?php

namespace App\Models;

use Database\Factories\KnowledgePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A page in a team's knowledge base, edited as a tree of editor blocks.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $parent_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string|null $title
 * @property array<int, array<string, mixed>>|null $content
 * @property int $position
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read KnowledgePage|null $parent
 * @property-read Collection<int, KnowledgePage> $children
 * @property-read User|null $author
 * @property-read User|null $editor
 */
#[Fillable([
    'team_id',
    'parent_id',
    'created_by',
    'updated_by',
    'title',
    'content',
    'position',
    'version',
])]
class KnowledgePage extends Model
{
    /** @use HasFactory<KnowledgePageFactory> */
    use HasFactory;

    /**
     * The deepest nesting the ancestor walk will follow before giving up.
     */
    protected const int MAX_DEPTH = 64;

    /**
     * Get the team that owns the page.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the page this page is nested under.
     *
     * @return BelongsTo<KnowledgePage, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(KnowledgePage::class, 'parent_id');
    }

    /**
     * Get the pages nested directly under this page, in display order.
     *
     * @return HasMany<KnowledgePage, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(KnowledgePage::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Get the user who created the page.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last saved the page.
     *
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the pages above this one, from the root page down to the direct parent.
     *
     * @return Collection<int, KnowledgePage>
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection;
        $current = $this->parent;

        while ($current && $ancestors->count() < self::MAX_DEPTH) {
            $ancestors->prepend($current);

            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Count every page nested under this one, at any depth.
     */
    public function descendantCount(): int
    {
        $count = 0;
        $parentIds = [$this->id];

        while ($parentIds !== []) {
            $parentIds = static::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->all();

            $count += count($parentIds);
        }

        return $count;
    }

    /**
     * Get the title as shown to users.
     */
    public function displayTitle(): string
    {
        return filled($this->title) ? $this->title : __('Untitled');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
