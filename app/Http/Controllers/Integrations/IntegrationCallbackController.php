<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Integrations\SyncIntegrations;
use App\Enums\IntegrationStatus;
use App\Http\Controllers\Controller;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Where the provider sends the user back after they authorized (or refused) an app.
 *
 * The query string the provider appends is not trusted: the outcome is read
 * from the provider itself by refreshing the team's connections.
 */
class IntegrationCallbackController extends Controller
{
    public function __invoke(Request $request, Team $current_team, IntegrationProvider $provider, SyncIntegrations $sync): RedirectResponse
    {
        try {
            $sync->handle($current_team);
        } catch (IntegrationProviderException $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => __(':provider could not be reached. The connection status may be out of date.', ['provider' => $provider->name()])]);

            return to_route('integrations.index', ['current_team' => $current_team->slug]);
        }

        $integration = $current_team->integrations()
            ->where('provider', $provider->id())
            ->where('provider_connection_id', $provider->callbackConnectionId($request))
            ->first();

        Inertia::flash('toast', match ($integration?->status) {
            IntegrationStatus::Active => ['type' => 'success', 'message' => __(':name connected.', ['name' => $integration->name])],
            IntegrationStatus::Initiated => ['type' => 'info', 'message' => __('The :name connection is still pending.', ['name' => $integration->name])],
            null => ['type' => 'warning', 'message' => __('We could not find that connection. Please try again.')],
            default => ['type' => 'error', 'message' => trim(__('Connecting :name failed.', ['name' => $integration->name]).' '.$integration->status_reason)],
        });

        return to_route('integrations.index', ['current_team' => $current_team->slug]);
    }
}
