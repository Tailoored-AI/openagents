<?php

namespace App\Http\Controllers\Knowledge;

use App\Actions\Knowledge\CreateKnowledgePage;
use App\Actions\Knowledge\SaveKnowledgePage;
use App\Data\KnowledgePageDetail;
use App\Data\KnowledgePageNode;
use App\Exceptions\KnowledgePageConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgePageRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgePageRequest;
use App\Models\KnowledgePage;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgePageController extends Controller
{
    /**
     * Show the team's knowledge base as a tree of pages.
     */
    public function index(Request $request, Team $current_team): Response
    {
        $pages = $current_team->knowledgePages()
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'title', 'updated_at']);

        return Inertia::render('knowledge/index', [
            'team' => $request->user()->toUserTeam($current_team),
            'pages' => KnowledgePageNode::tree($pages),
            'permissions' => $request->user()->toTeamPermissions($current_team),
        ]);
    }

    /**
     * Add a page and open it in the editor.
     */
    public function store(StoreKnowledgePageRequest $request, Team $current_team, CreateKnowledgePage $create): RedirectResponse
    {
        Gate::authorize('create', [KnowledgePage::class, $current_team]);

        $page = $create->handle(
            $current_team,
            $request->user(),
            $request->validated('title'),
            $request->validated('parent_id'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('knowledge.show', ['current_team' => $current_team->slug, 'knowledge_page' => $page->id]);
    }

    /**
     * Open a page in the editor.
     */
    public function show(Request $request, Team $current_team, KnowledgePage $knowledge_page): Response
    {
        Gate::authorize('view', $knowledge_page);

        $knowledge_page->load(['author', 'editor']);

        return Inertia::render('knowledge/show', [
            'team' => $request->user()->toUserTeam($current_team),
            'page' => KnowledgePageDetail::fromPage($knowledge_page, $request->user()->can('delete', $knowledge_page)),
            'ancestors' => $knowledge_page->ancestors()
                ->map(fn (KnowledgePage $ancestor) => ['id' => $ancestor->id, 'title' => $ancestor->title])
                ->values(),
            'children' => $knowledge_page->children
                ->map(fn (KnowledgePage $child) => ['id' => $child->id, 'title' => $child->title])
                ->values(),
        ]);
    }

    /**
     * Save the editor's changes to a page.
     *
     * This is a plain JSON endpoint for the editor's autosave, not an Inertia visit.
     */
    public function update(UpdateKnowledgePageRequest $request, Team $current_team, KnowledgePage $knowledge_page, SaveKnowledgePage $save): JsonResponse
    {
        Gate::authorize('update', $knowledge_page);

        try {
            $page = $save->handle($knowledge_page, $request->user(), $request->changes());
        } catch (KnowledgePageConflictException $exception) {
            $current = $exception->page->load('editor');

            return response()->json([
                'message' => $current->editor
                    ? __('This page was changed by :name. Reload to see the latest version.', ['name' => $current->editor->name])
                    : __('This page was changed elsewhere. Reload to see the latest version.'),
                'version' => $current->version,
                'updatedAt' => $current->updated_at?->toISOString(),
                'updatedBy' => $current->editor?->name,
            ], 409);
        }

        return response()->json([
            'version' => $page->version,
            'updatedAt' => $page->updated_at?->toISOString(),
        ]);
    }

    /**
     * Delete a page along with every page nested under it.
     */
    public function destroy(Team $current_team, KnowledgePage $knowledge_page): RedirectResponse
    {
        Gate::authorize('delete', $knowledge_page);

        $parentId = $knowledge_page->parent_id;
        $title = $knowledge_page->displayTitle();

        $knowledge_page->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title deleted.', ['title' => $title])]);

        return $parentId
            ? to_route('knowledge.show', ['current_team' => $current_team->slug, 'knowledge_page' => $parentId])
            : to_route('knowledge.index', ['current_team' => $current_team->slug]);
    }
}
