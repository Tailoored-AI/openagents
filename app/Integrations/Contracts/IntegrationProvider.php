<?php

namespace App\Integrations\Contracts;

use App\Integrations\Data\ConnectableApp;
use App\Integrations\Data\Connection;
use App\Integrations\Data\ConnectionLink;
use App\Integrations\Data\ToolResult;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * A third-party service that brokers connections between teams and the apps they use.
 *
 * Connections are team-scoped: every method that touches credentials receives
 * the Team, and the implementation derives its own identifiers from it so a
 * caller can never reach another team's credentials.
 */
interface IntegrationProvider
{
    /**
     * Get the identifier stored on integrations brokered by this provider.
     */
    public function id(): string;

    /**
     * Get the name shown to users.
     */
    public function name(): string;

    /**
     * Get the URL of the dashboard where apps are enabled, if the provider has one.
     */
    public function dashboardUrl(): ?string;

    /**
     * Determine if the provider has the credentials it needs.
     */
    public function isConfigured(): bool;

    /**
     * Get the apps teams may connect.
     *
     * @return Collection<int, ConnectableApp>
     *
     * @throws IntegrationProviderException
     */
    public function catalog(): Collection;

    /**
     * Get a single connectable app, or null when the provider does not know it.
     *
     * @throws IntegrationProviderException
     */
    public function app(string $appId): ?ConnectableApp;

    /**
     * Start connecting an app to the team and return where the user must go to authorize it.
     *
     * @throws IntegrationProviderException
     */
    public function connect(Team $team, ConnectableApp $app, string $callbackUrl): ConnectionLink;

    /**
     * Get the current state of several connections.
     *
     * Each entry is the connection, null when the provider no longer knows it,
     * or the exception that prevented it from being fetched.
     *
     * @param  list<string>  $connectionIds
     * @return array<string, Connection|IntegrationProviderException|null>
     */
    public function connections(array $connectionIds): array;

    /**
     * Remove a connection. A connection the provider no longer knows counts as removed.
     *
     * @throws IntegrationProviderException
     */
    public function disconnect(string $connectionId): void;

    /**
     * Get the connection the provider is reporting on when it sends the user back to the callback route.
     */
    public function callbackConnectionId(Request $request): ?string;

    /**
     * Execute a tool with the credentials the team connected for the tool's app.
     *
     * Pass one of the team's own connections to pin the account when the team
     * connected the same app more than once.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws IntegrationProviderException
     * @throws \InvalidArgumentException when the integration belongs to another team or another provider
     */
    public function executeTool(Team $team, string $toolSlug, array $arguments = [], ?Integration $integration = null): ToolResult;
}
