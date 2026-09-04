<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $team = Team::factory()->create();

    $this->get(route('agents.index', ['current_team' => $team->slug]))->assertRedirect(route('login'));
});

test('users cannot reach the agent library of a team they do not belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user);

    $this->get(route('agents.index', ['current_team' => $team->slug]))->assertForbidden();
});

test('team members see the agent library', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user);

    $this->get(route('agents.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agents/index'));
});
