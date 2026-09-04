<?php

use App\Enums\TeamRole;
use App\Models\KnowledgePage;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a minimal editor document with one paragraph.
 *
 * @return array<int, array<string, mixed>>
 */
function knowledgeParagraph(string $text): array
{
    return [
        [
            'id' => (string) Str::uuid(),
            'type' => 'paragraph',
            'props' => ['textColor' => 'default', 'backgroundColor' => 'default', 'textAlignment' => 'left'],
            'content' => [['type' => 'text', 'text' => $text, 'styles' => []]],
            'children' => [],
        ],
    ];
}

/**
 * Create a team with a member of the given role.
 *
 * @return array{0: User, 1: Team}
 */
function knowledgeTeam(TeamRole $role = TeamRole::Owner): array
{
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    return [$user, $team];
}

test('guests are redirected to the login page', function () {
    $team = Team::factory()->create();
    $page = KnowledgePage::factory()->for($team)->create();

    $this->get(route('knowledge.index', ['current_team' => $team->slug]))->assertRedirect(route('login'));
    $this->get(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $page]))->assertRedirect(route('login'));
});

test('users cannot reach the knowledge base of a team they do not belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $page = KnowledgePage::factory()->for($team)->create(['title' => 'Secret']);

    $this->actingAs($user);

    $this->get(route('knowledge.index', ['current_team' => $team->slug]))->assertForbidden();
    $this->get(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $page]))->assertForbidden();
    $this->post(route('knowledge.store', ['current_team' => $team->slug]), ['title' => 'Intruder'])->assertForbidden();
    $this->patchJson(route('knowledge.update', ['current_team' => $team->slug, 'knowledge_page' => $page]), ['title' => 'Changed', 'version' => 1])->assertForbidden();
    $this->delete(route('knowledge.destroy', ['current_team' => $team->slug, 'knowledge_page' => $page]))->assertForbidden();

    $this->assertDatabaseHas('knowledge_pages', ['id' => $page->id, 'title' => 'Secret']);
    $this->assertDatabaseCount('knowledge_pages', 1);
});

test('the knowledge page lists the team pages as an ordered tree', function () {
    [$owner, $team] = knowledgeTeam();

    $second = KnowledgePage::factory()->for($team)->create(['title' => 'Second', 'position' => 2]);
    $first = KnowledgePage::factory()->for($team)->create(['title' => 'First', 'position' => 1]);
    KnowledgePage::factory()->childOf($first)->create(['title' => 'Nested']);
    KnowledgePage::factory()->create(['title' => 'Other team']);

    $response = $this
        ->actingAs($owner)
        ->get(route('knowledge.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('knowledge/index')
            ->where('team.slug', $team->slug)
            ->where('permissions.canManageKnowledge', true)
            ->has('pages', 2)
            ->where('pages.0.id', $first->id)
            ->where('pages.0.title', 'First')
            ->has('pages.0.children', 1)
            ->where('pages.0.children.0.title', 'Nested')
            ->where('pages.1.id', $second->id)
            ->has('pages.1.children', 0),
        );
});

test('team members can create a page and are taken to it', function () {
    [$member, $team] = knowledgeTeam(TeamRole::Member);

    KnowledgePage::factory()->for($team)->create(['position' => 3]);

    $response = $this
        ->actingAs($member)
        ->post(route('knowledge.store', ['current_team' => $team->slug]), ['title' => 'Onboarding']);

    $page = KnowledgePage::query()->where('title', 'Onboarding')->firstOrFail();

    $response
        ->assertRedirect(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $page]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Page created.']);

    expect($page->team_id)->toBe($team->id)
        ->and($page->parent_id)->toBeNull()
        ->and($page->created_by)->toBe($member->id)
        ->and($page->position)->toBe(4)
        ->and($page->version)->toBe(1);
});

test('a page can be created under another page of the same team', function () {
    [$owner, $team] = knowledgeTeam();

    $parent = KnowledgePage::factory()->for($team)->create();

    $this
        ->actingAs($owner)
        ->post(route('knowledge.store', ['current_team' => $team->slug]), ['parent_id' => $parent->id])
        ->assertRedirect();

    $this->assertDatabaseHas('knowledge_pages', [
        'team_id' => $team->id,
        'parent_id' => $parent->id,
        'title' => null,
        'position' => 1,
    ]);
});

test('a page cannot be nested under a page of another team', function () {
    [$owner, $team] = knowledgeTeam();

    $foreignParent = KnowledgePage::factory()->create();

    $this
        ->actingAs($owner)
        ->from(route('knowledge.index', ['current_team' => $team->slug]))
        ->post(route('knowledge.store', ['current_team' => $team->slug]), ['parent_id' => $foreignParent->id])
        ->assertSessionHasErrors('parent_id');

    $this->assertDatabaseCount('knowledge_pages', 1);
});

test('the editor page shows the page with its ancestors and subpages', function () {
    [$owner, $team] = knowledgeTeam();

    $root = KnowledgePage::factory()->for($team)->create(['title' => 'Root']);
    $middle = KnowledgePage::factory()->childOf($root)->create(['title' => 'Middle']);
    $page = KnowledgePage::factory()->childOf($middle)->for($owner, 'author')->withContent('Body text')->create(['title' => 'Leaf', 'version' => 4]);
    KnowledgePage::factory()->childOf($page)->create(['title' => 'Child A', 'position' => 1]);
    KnowledgePage::factory()->childOf($page)->create(['title' => 'Child B', 'position' => 2]);

    $response = $this
        ->actingAs($owner)
        ->get(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $page]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('knowledge/show')
            ->where('page.id', $page->id)
            ->where('page.title', 'Leaf')
            ->where('page.version', 4)
            ->where('page.parentId', $middle->id)
            ->where('page.content.0.type', 'paragraph')
            ->where('page.content.0.content.0.text', 'Body text')
            ->where('page.createdBy.id', $owner->id)
            ->where('page.canDelete', true)
            ->where('page.descendantCount', 2)
            ->has('ancestors', 2)
            ->where('ancestors.0.title', 'Root')
            ->where('ancestors.1.title', 'Middle')
            ->has('children', 2)
            ->where('children.0.title', 'Child A')
            ->where('children.1.title', 'Child B'),
        );
});

test('a page of another team cannot be opened through the current team', function () {
    [$owner, $team] = knowledgeTeam();

    $foreignPage = KnowledgePage::factory()->create();

    $this
        ->actingAs($owner)
        ->get(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $foreignPage]))
        ->assertNotFound();
});

test('autosave stores the title and content and returns the next version', function () {
    $this->freezeSecond();

    [$member, $team] = knowledgeTeam(TeamRole::Member);

    $page = KnowledgePage::factory()->for($team)->create(['title' => 'Draft', 'version' => 1]);
    $content = knowledgeParagraph('Hello team');

    $response = $this
        ->actingAs($member)
        ->patchJson(route('knowledge.update', ['current_team' => $team->slug, 'knowledge_page' => $page]), [
            'title' => 'Final',
            'content' => $content,
            'version' => 1,
        ]);

    $response
        ->assertOk()
        ->assertJson(['version' => 2, 'updatedAt' => now()->toISOString()]);

    $page->refresh();

    expect($page->title)->toBe('Final')
        ->and($page->content)->toEqual($content)
        ->and($page->version)->toBe(2)
        ->and($page->updated_by)->toBe($member->id);
});

test('autosave can change the title without touching the content', function () {
    [$owner, $team] = knowledgeTeam();

    $page = KnowledgePage::factory()->for($team)->withContent('Keep me')->create(['version' => 1]);

    $this
        ->actingAs($owner)
        ->patchJson(route('knowledge.update', ['current_team' => $team->slug, 'knowledge_page' => $page]), [
            'title' => null,
            'version' => 1,
        ])
        ->assertOk();

    $page->refresh();

    expect($page->title)->toBeNull()
        ->and($page->content[0]['content'][0]['text'])->toBe('Keep me');
});

test('a save based on an outdated version is rejected without changing the page', function () {
    [$owner, $team] = knowledgeTeam();
    $editor = User::factory()->create(['name' => 'Jamie']);

    $page = KnowledgePage::factory()->for($team)->for($editor, 'editor')->create(['title' => 'Current', 'version' => 3]);

    $response = $this
        ->actingAs($owner)
        ->patchJson(route('knowledge.update', ['current_team' => $team->slug, 'knowledge_page' => $page]), [
            'title' => 'Stale',
            'version' => 2,
        ]);

    $response
        ->assertStatus(409)
        ->assertJson([
            'message' => 'This page was changed by Jamie. Reload to see the latest version.',
            'version' => 3,
            'updatedBy' => 'Jamie',
        ]);

    $this->assertDatabaseHas('knowledge_pages', ['id' => $page->id, 'title' => 'Current', 'version' => 3]);
});

test('autosave rejects content that is not a non-empty list of blocks', function (mixed $content) {
    [$owner, $team] = knowledgeTeam();

    $page = KnowledgePage::factory()->for($team)->create(['version' => 1]);

    $this
        ->actingAs($owner)
        ->patchJson(route('knowledge.update', ['current_team' => $team->slug, 'knowledge_page' => $page]), [
            'content' => $content,
            'version' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');

    $this->assertDatabaseHas('knowledge_pages', ['id' => $page->id, 'version' => 1]);
})->with([
    'string' => 'not blocks',
    'empty list' => [[]],
    'block without a type' => [[['id' => 'abc', 'children' => []]]],
    'block with a bad id' => [[['id' => 1, 'type' => 'paragraph']]],
]);

test('autosave requires the version it builds on', function () {
    [$owner, $team] = knowledgeTeam();

    $page = KnowledgePage::factory()->for($team)->create();

    $this
        ->actingAs($owner)
        ->patchJson(route('knowledge.update', ['current_team' => $team->slug, 'knowledge_page' => $page]), ['title' => 'No version'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('version');
});

test('the author can delete their page along with its subpages', function () {
    [$member, $team] = knowledgeTeam(TeamRole::Member);

    $parent = KnowledgePage::factory()->for($team)->create(['title' => 'Parent']);
    $page = KnowledgePage::factory()->childOf($parent)->for($member, 'author')->create(['title' => 'Mine']);
    $child = KnowledgePage::factory()->childOf($page)->create();
    $grandchild = KnowledgePage::factory()->childOf($child)->create();

    $response = $this
        ->actingAs($member)
        ->delete(route('knowledge.destroy', ['current_team' => $team->slug, 'knowledge_page' => $page]));

    $response
        ->assertRedirect(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $parent]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Mine deleted.']);

    $this->assertModelMissing($page);
    $this->assertModelMissing($child);
    $this->assertModelMissing($grandchild);
    $this->assertModelExists($parent);
});

test('deleting a top-level page returns to the directory', function () {
    [$owner, $team] = knowledgeTeam();

    $page = KnowledgePage::factory()->for($team)->untitled()->create();

    $this
        ->actingAs($owner)
        ->delete(route('knowledge.destroy', ['current_team' => $team->slug, 'knowledge_page' => $page]))
        ->assertRedirect(route('knowledge.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Untitled deleted.']);

    $this->assertModelMissing($page);
});

test('members cannot delete pages they did not create', function () {
    [$member, $team] = knowledgeTeam(TeamRole::Member);

    $page = KnowledgePage::factory()->for($team)->create();

    $this
        ->actingAs($member)
        ->delete(route('knowledge.destroy', ['current_team' => $team->slug, 'knowledge_page' => $page]))
        ->assertForbidden();

    $this->assertModelExists($page);
});

test('owners and admins can delete any page of the team', function (TeamRole $role) {
    [$user, $team] = knowledgeTeam($role);

    $page = KnowledgePage::factory()->for($team)->create();

    $this
        ->actingAs($user)
        ->delete(route('knowledge.destroy', ['current_team' => $team->slug, 'knowledge_page' => $page]))
        ->assertRedirect();

    $this->assertModelMissing($page);
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('the editor page hides deletion from members who did not create the page', function () {
    [$member, $team] = knowledgeTeam(TeamRole::Member);

    $page = KnowledgePage::factory()->for($team)->create();

    $this
        ->actingAs($member)
        ->get(route('knowledge.show', ['current_team' => $team->slug, 'knowledge_page' => $page]))
        ->assertInertia(fn (Assert $inertia) => $inertia->where('page.canDelete', false));
});
