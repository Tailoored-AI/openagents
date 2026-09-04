<?php

namespace App\Integrations\Composio;

use App\Data\ComposioAuthConfig;
use App\Data\ComposioConnectedAccount;
use App\Data\ComposioToolkit;
use App\Enums\IntegrationStatus;
use App\Http\Clients\ComposioClient;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Data\ConnectableApp;
use App\Integrations\Data\Connection;
use App\Integrations\Data\ConnectionLink;
use App\Integrations\Data\ToolResult;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Brokers team connections through Composio.
 *
 * Composio does not enforce team boundaries: the project API key can address
 * any connected account by user id. Isolation therefore rests on always
 * deriving the Composio user id from the Team, which only this class does.
 */
class ComposioProvider implements IntegrationProvider
{
    public function __construct(protected ComposioClient $client)
    {
        //
    }

    public function id(): string
    {
        return 'composio';
    }

    public function name(): string
    {
        return 'Composio';
    }

    public function dashboardUrl(): ?string
    {
        return 'https://platform.composio.dev';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Get the auth configs of the Composio project, described by their toolkits.
     */
    public function catalog(): Collection
    {
        return $this->guard(function () {
            $authConfigs = $this->client->authConfigs();

            $toolkits = $this->client->toolkits(
                array_values($authConfigs->map(fn (ComposioAuthConfig $authConfig) => $authConfig->toolkitSlug)->all()),
            );

            return $authConfigs->map(fn (ComposioAuthConfig $authConfig) => $this->connectableApp(
                $authConfig,
                $toolkits[$authConfig->toolkitSlug] ?? ComposioToolkit::fallback($authConfig->toolkitSlug),
            ));
        });
    }

    public function app(string $appId): ?ConnectableApp
    {
        return $this->guard(function () use ($appId) {
            $authConfig = $this->client->authConfig($appId);

            if (! $authConfig) {
                return null;
            }

            return $this->connectableApp($authConfig, $this->client->toolkit($authConfig->toolkitSlug));
        });
    }

    public function connect(Team $team, ConnectableApp $app, string $callbackUrl): ConnectionLink
    {
        return $this->guard(function () use ($team, $app, $callbackUrl) {
            $link = $this->client->createConnectionLink(
                authConfigId: $app->id,
                userId: $this->userIdFor($team),
                callbackUrl: $callbackUrl,
            );

            return new ConnectionLink($link->connectedAccountId, $link->redirectUrl);
        });
    }

    public function connections(array $connectionIds): array
    {
        $connections = [];

        foreach ($this->client->connectedAccounts($connectionIds) as $id => $account) {
            $connections[$id] = match (true) {
                $account instanceof ComposioConnectedAccount => $this->connection($account),
                $account instanceof HttpClientException => $this->wrap($account),
                default => null,
            };
        }

        return $connections;
    }

    public function disconnect(string $connectionId): void
    {
        $this->guard(fn () => $this->client->deleteConnectedAccount($connectionId));
    }

    public function callbackConnectionId(Request $request): ?string
    {
        $connectionId = $request->input('connected_account_id');

        return is_string($connectionId) && $connectionId !== '' ? $connectionId : null;
    }

    public function executeTool(Team $team, string $toolSlug, array $arguments = [], ?Integration $integration = null): ToolResult
    {
        if ($integration && $integration->team_id !== $team->id) {
            throw new InvalidArgumentException("Connection [{$integration->id}] does not belong to team [{$team->id}].");
        }

        if ($integration && $integration->provider !== $this->id()) {
            throw new InvalidArgumentException("Connection [{$integration->id}] was not brokered by [{$this->id()}].");
        }

        return $this->guard(function () use ($team, $toolSlug, $arguments, $integration) {
            $result = $this->client->executeTool(
                userId: $this->userIdFor($team),
                toolSlug: $toolSlug,
                arguments: $arguments,
                connectedAccountId: $integration?->provider_connection_id,
            );

            return new ToolResult($result->successful, $result->data, $result->error, $result->logId);
        });
    }

    /**
     * Get the Composio user id under which the team's connections are stored.
     */
    protected function userIdFor(Team $team): string
    {
        return "team_{$team->id}";
    }

    /**
     * Describe an auth config as a connectable app.
     */
    protected function connectableApp(ComposioAuthConfig $authConfig, ComposioToolkit $toolkit): ConnectableApp
    {
        return new ConnectableApp(
            id: $authConfig->id,
            slug: $authConfig->toolkitSlug,
            name: $toolkit->name,
            description: $toolkit->description,
            logo: $authConfig->toolkitLogo ?? $toolkit->logo,
            authScheme: $authConfig->authScheme !== '' ? $authConfig->authScheme : null,
        );
    }

    /**
     * Describe a connected account as a connection.
     */
    protected function connection(ComposioConnectedAccount $account): Connection
    {
        return new Connection(
            id: $account->id,
            appSlug: $account->toolkitSlug,
            status: $this->status($account->status),
            statusReason: $account->statusReason,
        );
    }

    /**
     * Map a Composio connected account status onto a local status.
     */
    protected function status(?string $status): IntegrationStatus
    {
        return match (strtoupper((string) $status)) {
            'ACTIVE' => IntegrationStatus::Active,
            'INITIALIZING', 'INITIATED' => IntegrationStatus::Initiated,
            'EXPIRED' => IntegrationStatus::Expired,
            'INACTIVE' => IntegrationStatus::Inactive,
            default => IntegrationStatus::Failed,
        };
    }

    /**
     * Run a client call, translating transport failures into provider exceptions.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws IntegrationProviderException
     */
    protected function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (HttpClientException $exception) {
            throw $this->wrap($exception);
        }
    }

    /**
     * Wrap a transport failure in a provider exception.
     */
    protected function wrap(HttpClientException $exception): IntegrationProviderException
    {
        return new IntegrationProviderException(
            "Composio request failed: {$exception->getMessage()}",
            previous: $exception,
        );
    }
}
