<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\KnowledgePage;
use App\Models\Team;
use App\Models\User;

class KnowledgePagePolicy
{
    /**
     * Determine whether the user can read the page.
     */
    public function view(User $user, KnowledgePage $page): bool
    {
        return $user->belongsToTeam($page->team);
    }

    /**
     * Determine whether the user can add a page to the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can edit the page.
     */
    public function update(User $user, KnowledgePage $page): bool
    {
        return $user->belongsToTeam($page->team);
    }

    /**
     * Determine whether the user can delete the page and everything nested under it.
     */
    public function delete(User $user, KnowledgePage $page): bool
    {
        return $page->created_by === $user->id
            || $user->hasTeamPermission($page->team, TeamPermission::ManageKnowledge);
    }
}
