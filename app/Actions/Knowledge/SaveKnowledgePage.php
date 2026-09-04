<?php

namespace App\Actions\Knowledge;

use App\Exceptions\KnowledgePageConflictException;
use App\Models\KnowledgePage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveKnowledgePage
{
    /**
     * Store the changes made to a page, provided they build on its current version.
     *
     * Each save bumps the version; a save carrying an older version means
     * someone else saved in between, so it is refused rather than silently
     * overwriting their work.
     *
     * @param  array{title?: string|null, content?: array<mixed>|null, version: int}  $attributes
     *
     * @throws KnowledgePageConflictException
     */
    public function handle(KnowledgePage $page, User $user, array $attributes): KnowledgePage
    {
        return DB::transaction(function () use ($page, $user, $attributes) {
            $current = KnowledgePage::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->version !== $attributes['version']) {
                throw new KnowledgePageConflictException($current);
            }

            $current
                ->fill(Arr::only($attributes, ['title', 'content']))
                ->fill([
                    'updated_by' => $user->id,
                    'version' => $current->version + 1,
                ])
                ->save();

            return $current;
        });
    }
}
