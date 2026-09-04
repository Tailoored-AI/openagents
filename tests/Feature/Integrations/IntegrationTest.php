<?php

use App\Enums\IntegrationStatus;
use App\Enums\TeamRole;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Data\ConnectableApp;
use App\Integrations\Data\Connection;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

beforeEach(function () {
    config()->set('services.composio.key', 'composio-test-key');
});

/**
 * Build a Composio auth config payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function composioAuthConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'ac_github',
        'name' => 'GitHub',
        'toolkit' => ['slug' => 'github', 'logo' => 'https://logos.composio.dev/api/github'],
        'auth_scheme' => 'OAUTH2',
        'is_composio_managed' => true,
        'status' => 'ENABLED',
    ], $overrides);
}

/**
 * Build a Composio connected account payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function composioConnectedAccount(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'ca_github',
        'toolkit' => ['slug' => 'github'],
        'auth_config' => ['id' => 'ac_github', 'is_composio_managed' => true],
        'status' => 'ACTIVE',
        'status_reason' => null,
        'user_id' => 'team_1',
    ], $overrides);
}

/**
 * Build a Composio auth config list payload.
 *
 * @param  array<string, mixed>  ...$authConfigs
 * @return array<string, mixed>
 */
function composioCatalog(array ...$authConfigs): array
{
    return ['items' => array_values($authConfigs), 'next_cursor' => null];
}

/**
 * Build a Composio toolkit payload.
 *
 * @return array<string, mixed>
 */
function composioToolkit(string $slug, string $name, ?string $description = null): array
{
    return [
        'slug' => $slug,
        'name' => $name,
        'meta' => [
            'description' => $description,
            'logo' => "https://logos.composio.dev/api/{$slug}",
        ],
    ];
}

test('guests are redirected to the login page', function () {
    $team = Team::factory()->create();

    $response = $this->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertRedirect(route('login'));
});

test('users cannot view the integrations of a team they do not belong to', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertForbidden();
});

test('the integrations page lists the composio apps with the team connection status', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(
            composioAuthConfig(['id' => 'ac_slack', 'name' => 'slack (workshop)', 'toolkit' => ['slug' => 'slack', 'logo' => null]]),
            composioAuthConfig(['id' => 'ac_github', 'name' => 'github-oynvrz']),
        )),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub', 'GitHub is a code hosting platform.')),
        '*/toolkits/slack' => Http::response(composioToolkit('slack', 'Slack')),
        '*/connected_accounts/ca_github' => Http::response(composioConnectedAccount(['id' => 'ca_github', 'status' => 'ACTIVE'])),
    ]);

    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    Integration::factory()->for($team)->for($owner, 'connector')->create([
        'app_slug' => 'github',
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_github',
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/index')
            ->where('team.slug', $team->slug)
            ->where('provider.name', 'Composio')
            ->where('provider.dashboardUrl', 'https://platform.composio.dev')
            ->where('providerConfigured', true)
            ->where('providerError', null)
            ->where('permissions.canManageIntegrations', true)
            ->has('integrations', 2)
            ->where('integrations.0.name', 'GitHub')
            ->where('integrations.0.description', 'GitHub is a code hosting platform.')
            ->where('integrations.0.appId', 'ac_github')
            ->where('integrations.0.logo', 'https://logos.composio.dev/api/github')
            ->where('integrations.0.authScheme', 'OAUTH2')
            ->where('integrations.0.isAvailable', true)
            ->where('integrations.0.status', 'active')
            ->where('integrations.0.statusLabel', 'Connected')
            ->where('integrations.0.connectedBy', 'Taylor Otwell')
            ->where('integrations.1.name', 'Slack')
            ->where('integrations.1.description', null)
            ->where('integrations.1.logo', 'https://logos.composio.dev/api/slack')
            ->where('integrations.1.id', null)
            ->where('integrations.1.status', null),
        );
});

test('the integrations page refreshes connection statuses from composio', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig())),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/ca_github' => Http::response(composioConnectedAccount([
            'status' => 'EXPIRED',
            'status_reason' => 'The access token could not be refreshed.',
        ])),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->for($team)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_github',
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('integrations.0.status', 'expired')
        ->where('integrations.0.statusLabel', 'Expired')
        ->where('integrations.0.statusReason', 'The access token could not be refreshed.'),
    );

    $this->assertDatabaseHas('integrations', [
        'id' => $integration->id,
        'status' => IntegrationStatus::Expired->value,
        'status_reason' => 'The access token could not be refreshed.',
    ]);
});

test('the integrations page forgets connections composio no longer knows', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig())),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/ca_gone' => Http::response(['error' => ['message' => 'Not found']], 404),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->for($team)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_gone',
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('integrations', 1)
        ->where('integrations.0.id', null)
        ->where('integrations.0.status', null),
    );

    $this->assertModelMissing($integration);
});

test('the integrations page shows stored connections when composio is unreachable', function () {
    Exceptions::fake();
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::failedConnection(),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    Integration::factory()->for($team)->create(['name' => 'GitHub']);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('providerError', 'Composio could not be reached. Showing the last known connection status.')
            ->has('integrations', 1)
            ->where('integrations.0.name', 'GitHub')
            ->where('integrations.0.status', 'active')
            ->where('integrations.0.isAvailable', true),
        );

    Exceptions::assertReported(IntegrationProviderException::class);
});

test('the integrations page marks connections whose app left the catalog as unavailable', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig())),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/ca_slack' => Http::response(composioConnectedAccount(['id' => 'ca_slack', 'toolkit' => ['slug' => 'slack']])),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    Integration::factory()->for($team)->create([
        'name' => 'Slack',
        'app_slug' => 'slack',
        'provider_app_id' => 'ac_slack_removed',
        'provider_connection_id' => 'ca_slack',
    ]);
    Integration::factory()->for($team)->create([
        'name' => 'Linear',
        'provider' => 'acme',
        'app_slug' => 'linear',
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('providerError', null)
        ->has('integrations', 3)
        ->where('integrations.0.name', 'GitHub')
        ->where('integrations.0.isAvailable', true)
        ->where('integrations.1.name', 'Linear')
        ->where('integrations.1.isAvailable', false)
        ->where('integrations.2.name', 'Slack')
        ->where('integrations.2.isAvailable', false),
    );
});

test('the integrations page reports when composio is not configured', function () {
    config()->set('services.composio.key', null);
    Http::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('providerConfigured', false)
            ->where('providerError', null)
            ->has('integrations', 0),
        );

    Http::assertNothingSent();
});

test('team members see the integrations without being able to manage them', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig())),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
    ]);

    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageIntegrations', false)
        ->has('integrations', 1),
    );
});

test('team owners and admins can start connecting an app', function (TeamRole $role) {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_github' => Http::response(composioAuthConfig(['name' => 'github-oynvrz'])),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/link' => Http::response([
            'link_token' => 'lt_123',
            'redirect_url' => 'https://connect.composio.dev/link/lt_123',
            'expires_at' => '2026-09-01T20:00:00Z',
            'connected_account_id' => 'ca_new',
        ], 201),
    ]);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    $response = $this
        ->actingAs($user)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response->assertRedirect('https://connect.composio.dev/link/lt_123');

    $this->assertDatabaseHas('integrations', [
        'team_id' => $team->id,
        'connected_by' => $user->id,
        'app_slug' => 'github',
        'name' => 'GitHub',
        'logo' => 'https://logos.composio.dev/api/github',
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_new',
        'status' => IntegrationStatus::Initiated->value,
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://backend.composio.dev/api/v3/connected_accounts/link'
        && $request->hasHeader('x-api-key', 'composio-test-key')
        && $request['auth_config_id'] === 'ac_github'
        && $request['user_id'] === "team_{$team->id}"
        && $request['callback_url'] === route('integrations.callback', ['current_team' => $team->slug]));
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('reconnecting an app replaces its previous connection', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_github' => Http::response(composioAuthConfig(['name' => 'github-oynvrz'])),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/ca_old' => Http::response(['success' => true]),
        '*/connected_accounts/link' => Http::response([
            'redirect_url' => 'https://connect.composio.dev/link/lt_456',
            'connected_account_id' => 'ca_new',
        ], 201),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->failed()->for($team)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_old',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response->assertRedirect('https://connect.composio.dev/link/lt_456');

    $this->assertDatabaseHas('integrations', [
        'id' => $integration->id,
        'provider_connection_id' => 'ca_new',
        'status' => IntegrationStatus::Initiated->value,
        'status_reason' => null,
    ]);

    Http::assertSentInOrder([
        fn (Request $request) => $request->method() === 'GET' && str_ends_with($request->url(), '/auth_configs/ac_github'),
        fn (Request $request) => $request->method() === 'GET' && str_ends_with($request->url(), '/toolkits/github'),
        fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/connected_accounts/link'),
        fn (Request $request) => $request->method() === 'DELETE' && $request->url() === 'https://backend.composio.dev/api/v3/connected_accounts/ca_old',
    ]);
});

test('a failed composio link request keeps the previous connection', function () {
    Exceptions::fake();
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_github' => Http::response(composioAuthConfig(['name' => 'github-oynvrz'])),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/link' => Http::response(['error' => ['message' => 'Internal error']], 500),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->expired()->for($team)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_old',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Composio could not be reached. Please try again.']);

    $this->assertDatabaseHas('integrations', [
        'id' => $integration->id,
        'provider_connection_id' => 'ca_old',
        'status' => IntegrationStatus::Expired->value,
    ]);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
});

test('a previous connection composio cannot remove is reported without blocking the new one', function () {
    Exceptions::fake();
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_github' => Http::response(composioAuthConfig(['name' => 'github-oynvrz'])),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/ca_old' => Http::response(['error' => ['message' => 'Internal error']], 500),
        '*/connected_accounts/link' => Http::response([
            'redirect_url' => 'https://connect.composio.dev/link/lt_789',
            'connected_account_id' => 'ca_new',
        ], 201),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->failed()->for($team)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_old',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response->assertRedirect('https://connect.composio.dev/link/lt_789');

    $this->assertDatabaseHas('integrations', [
        'id' => $integration->id,
        'provider_connection_id' => 'ca_new',
        'status' => IntegrationStatus::Initiated->value,
    ]);

    Exceptions::assertReported(IntegrationProviderException::class);
});

test('team members cannot connect apps', function () {
    Http::preventStrayRequests();

    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseCount('integrations', 0);
});

test('connecting an app requires an auth config', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), []);

    $response->assertSessionHasErrors('app_id');

    $this->assertDatabaseCount('integrations', 0);
});

test('connecting an app composio does not know is rejected', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_unknown' => Http::response(['error' => ['message' => 'Not found']], 404),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_unknown',
        ]);

    $response
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'The selected app is no longer available to connect.']);

    $this->assertDatabaseCount('integrations', 0);
});

test('an app id that is not a plain identifier is rejected before composio is asked', function (string $appId) {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => $appId,
        ]);

    $response->assertSessionHasErrors('app_id');

    Http::assertNothingSent();
})->with([
    'path traversal' => '../connected_accounts/ca_other',
    'query string' => 'ac_github?limit=1',
    'slash' => 'ac_github/extra',
]);

test('an app that is already connected cannot be connected again', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_github' => Http::response(composioAuthConfig(['name' => 'github-oynvrz'])),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->for($team)->create([
        'name' => 'GitHub',
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_current',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'info', 'message' => 'GitHub is already connected. Disconnect it first to connect a different account.']);

    $this->assertDatabaseHas('integrations', [
        'id' => $integration->id,
        'provider_connection_id' => 'ca_current',
        'status' => IntegrationStatus::Active->value,
    ]);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE' || str_ends_with($request->url(), '/connected_accounts/link'));
});

test('a failed composio link request does not create a connection', function () {
    Exceptions::fake();
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs/ac_github' => Http::response(composioAuthConfig(['name' => 'github-oynvrz'])),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/link' => Http::response(['error' => ['message' => 'Internal error']], 500),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->post(route('integrations.store', ['current_team' => $team->slug]), [
            'app_id' => 'ac_github',
        ]);

    $response
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Composio could not be reached. Please try again.']);

    $this->assertDatabaseCount('integrations', 0);
});

test('the callback marks the connection as active once composio confirms it', function () {
    $this->freezeSecond();

    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_new' => Http::response(composioConnectedAccount(['id' => 'ca_new', 'status' => 'ACTIVE'])),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->initiated()->for($team)->create([
        'name' => 'GitHub',
        'provider_connection_id' => 'ca_new',
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.callback', [
            'current_team' => $team->slug,
            'status' => 'success',
            'connected_account_id' => 'ca_new',
        ]));

    $response
        ->assertRedirect(route('integrations.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'GitHub connected.']);

    $integration->refresh();

    expect($integration->status)->toBe(IntegrationStatus::Active)
        ->and($integration->connected_at)->toEqual(now());
});

test('the callback reports a connection composio rejected', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_new' => Http::response(composioConnectedAccount([
            'id' => 'ca_new',
            'status' => 'FAILED',
            'status_reason' => 'The user denied access.',
        ])),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->initiated()->for($team)->create([
        'name' => 'GitHub',
        'provider_connection_id' => 'ca_new',
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.callback', [
            'current_team' => $team->slug,
            'status' => 'failed',
            'connected_account_id' => 'ca_new',
        ]));

    $response
        ->assertRedirect(route('integrations.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Connecting GitHub failed. The user denied access.']);

    $this->assertDatabaseHas('integrations', [
        'id' => $integration->id,
        'status' => IntegrationStatus::Failed->value,
        'status_reason' => 'The user denied access.',
        'connected_at' => null,
    ]);
});

test('the callback warns when the connection is unknown', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.callback', [
            'current_team' => $team->slug,
            'connected_account_id' => 'ca_unknown',
        ]));

    $response
        ->assertRedirect(route('integrations.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => 'We could not find that connection. Please try again.']);
});

test('the callback tolerates a malformed connected account id', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.callback', ['current_team' => $team->slug]).'?connected_account_id[]=ca_new');

    $response
        ->assertRedirect(route('integrations.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => 'We could not find that connection. Please try again.']);
});

test('team owners and admins can disconnect an app', function (TeamRole $role) {
    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_github' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    $integration = Integration::factory()->for($team)->create([
        'name' => 'GitHub',
        'provider_connection_id' => 'ca_github',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $integration]));

    $response
        ->assertRedirect(route('integrations.index', ['current_team' => $team->slug]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'GitHub disconnected.']);

    $this->assertModelMissing($integration);

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://backend.composio.dev/api/v3/connected_accounts/ca_github');
})->with([
    'owner' => TeamRole::Owner,
    'admin' => TeamRole::Admin,
]);

test('disconnecting an app composio already forgot still removes it', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_github' => Http::response(['error' => ['message' => 'Not found']], 404),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->for($team)->create([
        'provider_connection_id' => 'ca_github',
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $integration]));

    $response->assertRedirect(route('integrations.index', ['current_team' => $team->slug]));

    $this->assertModelMissing($integration);
});

test('a failed composio delete keeps the connection', function () {
    Exceptions::fake();
    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_github' => Http::response(['error' => ['message' => 'Internal error']], 500),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $integration = Integration::factory()->for($team)->create([
        'name' => 'GitHub',
        'provider_connection_id' => 'ca_github',
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $integration]));

    $response
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Composio could not be reached. GitHub was not disconnected.']);

    $this->assertModelExists($integration);
});

test('team members cannot disconnect apps', function () {
    Http::preventStrayRequests();

    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $integration = Integration::factory()->for($team)->create();

    $response = $this
        ->actingAs($member)
        ->delete(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $integration]));

    $response->assertForbidden();

    $this->assertModelExists($integration);
});

test('connections of other teams cannot be disconnected', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $otherTeamIntegration = Integration::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->delete(route('integrations.destroy', ['current_team' => $team->slug, 'integration' => $otherTeamIntegration]));

    $response->assertNotFound();

    $this->assertModelExists($otherTeamIntegration);
});

test('the integrations page names an app after its toolkit when composio cannot describe it', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig(['name' => 'github-oynvrz']))),
        '*/toolkits/github' => Http::response(['error' => ['message' => 'Not found']], 404),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('integrations.0.name', 'Github')
        ->where('integrations.0.description', null)
        ->where('integrations.0.logo', 'https://logos.composio.dev/api/github'),
    );
});

test('toolkit details are cached between page views', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig())),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)->get(route('integrations.index', ['current_team' => $team->slug]))->assertOk();
    $this->actingAs($owner)->get(route('integrations.index', ['current_team' => $team->slug]))->assertOk();

    expect(Http::recorded(fn (Request $request) => str_contains($request->url(), '/toolkits/'))->count())->toBe(1);
});

test('a user in two teams only sees the connections of the team they are viewing', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::response(composioCatalog(composioAuthConfig())),
        '*/toolkits/github' => Http::response(composioToolkit('github', 'GitHub')),
        '*/connected_accounts/ca_alpha' => Http::response(composioConnectedAccount(['id' => 'ca_alpha'])),
        '*/connected_accounts/ca_beta' => Http::response(composioConnectedAccount(['id' => 'ca_beta'])),
    ]);

    $user = User::factory()->create();
    $alpha = Team::factory()->create(['name' => 'Alpha']);
    $beta = Team::factory()->create(['name' => 'Beta']);
    $alpha->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $beta->members()->attach($user, ['role' => TeamRole::Member->value]);

    $alphaIntegration = Integration::factory()->for($alpha)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_alpha',
    ]);
    $betaIntegration = Integration::factory()->for($beta)->create([
        'provider_app_id' => 'ac_github',
        'provider_connection_id' => 'ca_beta',
    ]);

    $this
        ->actingAs($user)
        ->get(route('integrations.index', ['current_team' => $alpha->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('integrations', 1)
            ->where('integrations.0.id', $alphaIntegration->id)
            ->where('permissions.canManageIntegrations', true),
        );

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'ca_beta'));

    $this
        ->actingAs($user)
        ->get(route('integrations.index', ['current_team' => $beta->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('integrations', 1)
            ->where('integrations.0.id', $betaIntegration->id)
            ->where('permissions.canManageIntegrations', false),
        );
});

test('a team only finds its own working connection to an app', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    Integration::factory()->for($otherTeam)->create(['app_slug' => 'github']);
    Integration::factory()->expired()->for($team)->create(['app_slug' => 'github']);
    Integration::factory()->for($team)->create(['app_slug' => 'linear', 'provider' => 'acme']);
    $connected = Integration::factory()->for($team)->create(['app_slug' => 'slack']);

    expect($team->activeIntegration('github'))->toBeNull()
        ->and($team->activeIntegration('linear'))->toBeNull()
        ->and($team->activeIntegration('slack')?->is($connected))->toBeTrue();
});

test('the integrations page renders whichever provider is bound to the contract', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    Integration::factory()->for($team)->create([
        'provider' => 'acme',
        'provider_app_id' => 'app_linear',
        'provider_connection_id' => 'conn_1',
        'app_slug' => 'linear',
    ]);

    $provider = mock(IntegrationProvider::class);
    $provider->shouldReceive('id')->andReturn('acme');
    $provider->shouldReceive('name')->andReturn('Acme Connect');
    $provider->shouldReceive('dashboardUrl')->andReturn(null);
    $provider->shouldReceive('isConfigured')->andReturn(true);
    $provider->shouldReceive('catalog')->once()->andReturn(collect([
        new ConnectableApp(id: 'app_linear', slug: 'linear', name: 'Linear', description: null, logo: null, authScheme: 'oauth'),
    ]));
    $provider->shouldReceive('connections')->once()->with(['conn_1'])->andReturn([
        'conn_1' => new Connection(id: 'conn_1', appSlug: 'linear', status: IntegrationStatus::Expired, statusReason: 'Token revoked.'),
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('integrations.index', ['current_team' => $team->slug]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider.name', 'Acme Connect')
            ->where('provider.dashboardUrl', null)
            ->where('providerConfigured', true)
            ->has('integrations', 1)
            ->where('integrations.0.appId', 'app_linear')
            ->where('integrations.0.isAvailable', true)
            ->where('integrations.0.status', 'expired')
            ->where('integrations.0.statusReason', 'Token revoked.'),
        );

    Http::assertNothingSent();
});
