<?php

namespace App\Actions\Knowledge;

use App\Models\KnowledgePage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateKnowledgePage
{
    /**
     * Add a page to the team's knowledge base, after its siblings.
     */
    public function handle(Team $team, User $user, ?string $title, ?int $parentId): KnowledgePage
    {
        return DB::transaction(function () use ($team, $user, $title, $parentId) {
            $position = (int) $team->knowledgePages()
                ->where('parent_id', $parentId)
                ->max('position');

            return $team->knowledgePages()->create([
                'parent_id' => $parentId,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'title' => $title,
                'position' => $position + 1,
                'version' => 1,
            ]);
        });
    }
}
