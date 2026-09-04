<?php

use App\Enums\IntegrationStatus;
use App\Http\Clients\ComposioException;
use App\Integrations\Composio\ComposioProvider;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Data\Connection;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.composio.key', 'composio-test-key');
});

test('composio is the bound integration provider', function () {
    expect(app(IntegrationProvider::class))->toBeInstanceOf(ComposioProvider::class);
});

test('tools are executed under the team composio user id', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/tools/execute/GITHUB_LIST_REPOSITORIES' => Http::response([
            'data' => ['repositories' => [['name' => 'laravel']]],
            'error' => null,
            'successful' => true,
            'log_id' => 'log_123',
        ]),
    ]);

    $team = Team::factory()->create();

    $result = app(IntegrationProvider::class)->executeTool($team, 'GITHUB_LIST_REPOSITORIES', ['per_page' => 5]);

    expect($result->successful)->toBeTrue()
        ->and($result->data)->toBe(['repositories' => [['name' => 'laravel']]])
        ->and($result->error)->toBeNull()
        ->and($result->reference)->toBe('log_123');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://backend.composio.dev/api/v3/tools/execute/GITHUB_LIST_REPOSITORIES'
        && $request->hasHeader('x-api-key', 'composio-test-key')
        && $request['user_id'] === "team_{$team->id}"
        && (array) $request['arguments'] === ['per_page' => 5]
        && ! array_key_exists('connected_account_id', $request->data()));
});

test('one of the team connections can pin the account a tool runs with', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/tools/execute/GITHUB_LIST_REPOSITORIES' => Http::response(['data' => [], 'successful' => true]),
    ]);

    $team = Team::factory()->create();
    $integration = Integration::factory()->for($team)->create(['provider_connection_id' => 'ca_team']);

    app(IntegrationProvider::class)->executeTool($team, 'GITHUB_LIST_REPOSITORIES', [], $integration);

    Http::assertSent(fn (Request $request) => $request['user_id'] === "team_{$team->id}"
        && $request['connected_account_id'] === 'ca_team'
        && str_contains($request->body(), '"arguments":{}'));
});

test('a connection of another team cannot be used to execute a tool', function () {
    Http::fake();

    $team = Team::factory()->create();
    $otherTeamIntegration = Integration::factory()->create();

    expect(fn () => app(IntegrationProvider::class)->executeTool($team, 'GITHUB_LIST_REPOSITORIES', [], $otherTeamIntegration))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

test('a connection of another provider cannot be used to execute a tool', function () {
    Http::fake();

    $team = Team::factory()->create();
    $foreignIntegration = Integration::factory()->for($team)->create(['provider' => 'acme']);

    expect(fn () => app(IntegrationProvider::class)->executeTool($team, 'GITHUB_LIST_REPOSITORIES', [], $foreignIntegration))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

test('a successful response without a json body surfaces as a provider exception', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/tools/execute/GITHUB_LIST_REPOSITORIES' => Http::response('<html>Maintenance</html>', 200, ['Content-Type' => 'text/html']),
        '*/connected_accounts/ca_1' => Http::response('', 200),
    ]);

    $team = Team::factory()->create();
    $provider = app(IntegrationProvider::class);

    try {
        $provider->executeTool($team, 'GITHUB_LIST_REPOSITORIES');
    } catch (IntegrationProviderException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ComposioException::class);
    }

    expect($provider->connections(['ca_1'])['ca_1'])->toBeInstanceOf(IntegrationProviderException::class);
});

test('a tool failure reported by composio is returned to the caller', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/tools/execute/GITHUB_GET_A_REPOSITORY' => Http::response([
            'data' => [],
            'error' => 'Repository not found',
            'successful' => false,
            'log_id' => 'log_456',
        ]),
    ]);

    $team = Team::factory()->create();

    $result = app(IntegrationProvider::class)->executeTool($team, 'GITHUB_GET_A_REPOSITORY', ['owner' => 'laravel', 'repo' => 'missing']);

    expect($result->successful)->toBeFalse()
        ->and($result->error)->toBe('Repository not found')
        ->and($result->reference)->toBe('log_456');
});

test('executing a tool for an app the team has not connected throws a provider exception', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/tools/execute/GITHUB_LIST_REPOSITORIES' => Http::response([
            'error' => ['message' => 'No connected account found for user ID team_1 for toolkit github'],
        ], 404),
    ]);

    $team = Team::factory()->create();

    try {
        app(IntegrationProvider::class)->executeTool($team, 'GITHUB_LIST_REPOSITORIES');
    } catch (IntegrationProviderException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(RequestException::class);

        return;
    }

    $this->fail('No provider exception was thrown.');
});

test('an unreachable composio surfaces as a provider exception when loading the catalog', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/auth_configs*' => Http::failedConnection(),
    ]);

    try {
        app(IntegrationProvider::class)->catalog();
    } catch (IntegrationProviderException $exception) {
        expect($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);

        return;
    }

    $this->fail('No provider exception was thrown.');
});

test('connected account statuses are mapped onto local statuses', function (string $composioStatus, IntegrationStatus $expected) {
    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_1' => Http::response([
            'id' => 'ca_1',
            'toolkit' => ['slug' => 'github'],
            'status' => $composioStatus,
            'status_reason' => 'Because.',
        ]),
    ]);

    $connections = app(IntegrationProvider::class)->connections(['ca_1']);

    expect($connections['ca_1'])->toBeInstanceOf(Connection::class)
        ->and($connections['ca_1']->status)->toBe($expected)
        ->and($connections['ca_1']->appSlug)->toBe('github')
        ->and($connections['ca_1']->statusReason)->toBe('Because.');
})->with([
    'active' => ['ACTIVE', IntegrationStatus::Active],
    'initializing' => ['INITIALIZING', IntegrationStatus::Initiated],
    'initiated' => ['INITIATED', IntegrationStatus::Initiated],
    'expired' => ['EXPIRED', IntegrationStatus::Expired],
    'inactive' => ['INACTIVE', IntegrationStatus::Inactive],
    'failed' => ['FAILED', IntegrationStatus::Failed],
    'unknown' => ['SOMETHING_NEW', IntegrationStatus::Failed],
]);

test('connections are reported individually as found, gone or failed', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/connected_accounts/ca_found' => Http::response(['id' => 'ca_found', 'toolkit' => ['slug' => 'github'], 'status' => 'ACTIVE']),
        '*/connected_accounts/ca_gone' => Http::response(['error' => ['message' => 'Not found']], 404),
        '*/connected_accounts/ca_broken' => Http::response(['error' => ['message' => 'Internal error']], 500),
    ]);

    $connections = app(IntegrationProvider::class)->connections(['ca_found', 'ca_gone', 'ca_broken']);

    expect($connections['ca_found'])->toBeInstanceOf(Connection::class)
        ->and($connections['ca_gone'])->toBeNull()
        ->and($connections['ca_broken'])->toBeInstanceOf(IntegrationProviderException::class);
});

test('the callback identifies the connection by the connected account id composio appends', function () {
    $provider = app(IntegrationProvider::class);

    expect($provider->callbackConnectionId(HttpRequest::create('/callback', 'GET', ['connected_account_id' => 'ca_new'])))->toBe('ca_new')
        ->and($provider->callbackConnectionId(HttpRequest::create('/callback')))->toBeNull();
});
