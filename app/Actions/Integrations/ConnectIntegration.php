<?php

namespace App\Actions\Integrations;

use App\Enums\IntegrationStatus;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Data\ConnectableApp;
use App\Integrations\Exceptions\IntegrationAlreadyConnectedException;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Team;
use App\Models\User;

class ConnectIntegration
{
    public function __construct(protected IntegrationProvider $provider)
    {
        //
    }

    /**
     * Start connecting an app to the team and return the URL the user must visit to authorize it.
     *
     * A working connection is never replaced: it must be disconnected first.
     * A pending or broken one is discarded once the new authorization has
     * been created, so a failed request leaves the team where it was.
     *
     * @throws IntegrationAlreadyConnectedException
     * @throws IntegrationProviderException
     */
    public function handle(Team $team, User $user, ConnectableApp $app): string
    {
        $existing = $team->integrations()
            ->where('provider', $this->provider->id())
            ->where('provider_app_id', $app->id)
            ->first();

        if ($existing?->status === IntegrationStatus::Active) {
            throw new IntegrationAlreadyConnectedException($existing);
        }

        $link = $this->provider->connect(
            $team,
            $app,
            route('integrations.callback', ['current_team' => $team->slug]),
        );

        if ($existing) {
            $this->forget($existing->provider_connection_id);
        }

        $team->integrations()->updateOrCreate(
            ['provider' => $this->provider->id(), 'provider_app_id' => $app->id],
            [
                'connected_by' => $user->id,
                'app_slug' => $app->slug,
                'name' => $app->name,
                'logo' => $app->logo,
                'provider_connection_id' => $link->connectionId,
                'status' => IntegrationStatus::Initiated,
                'status_reason' => null,
                'connected_at' => null,
            ],
        );

        return $link->redirectUrl;
    }

    /**
     * Remove the superseded connection from the provider so it does not pile up there.
     *
     * The new authorization already exists, so a failure here is only reported.
     */
    protected function forget(string $connectionId): void
    {
        try {
            $this->provider->disconnect($connectionId);
        } catch (IntegrationProviderException $exception) {
            report($exception);
        }
    }
}
