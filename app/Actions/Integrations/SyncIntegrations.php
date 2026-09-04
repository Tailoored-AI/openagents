<?php

namespace App\Actions\Integrations;

use App\Enums\IntegrationStatus;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;
use App\Models\Team;

class SyncIntegrations
{
    public function __construct(protected IntegrationProvider $provider)
    {
        //
    }

    /**
     * Refresh the team's stored connections from the provider.
     *
     * Connections the provider no longer knows are forgotten. Every connection
     * that could be fetched is applied before the first failure is rethrown.
     *
     * @throws IntegrationProviderException
     */
    public function handle(Team $team): void
    {
        $integrations = $team->integrations()
            ->where('provider', $this->provider->id())
            ->get();

        if ($integrations->isEmpty()) {
            return;
        }

        $connections = $this->provider->connections(
            array_values($integrations->map(fn (Integration $integration) => $integration->provider_connection_id)->all()),
        );

        $failure = null;

        foreach ($integrations as $integration) {
            $connection = $connections[$integration->provider_connection_id] ?? null;

            if ($connection instanceof IntegrationProviderException) {
                $failure ??= $connection;

                continue;
            }

            if ($connection === null) {
                $integration->delete();

                continue;
            }

            $this->apply($integration, $connection->status, $connection->statusReason);
        }

        if ($failure) {
            throw $failure;
        }
    }

    /**
     * Store the status reported by the provider.
     */
    protected function apply(Integration $integration, IntegrationStatus $status, ?string $statusReason): void
    {
        $integration->fill([
            'status' => $status,
            'status_reason' => $statusReason,
            'connected_at' => $status === IntegrationStatus::Active
                ? ($integration->connected_at ?? now())
                : $integration->connected_at,
        ])->save();
    }
}
