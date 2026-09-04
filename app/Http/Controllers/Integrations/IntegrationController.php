<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\Integrations\ConnectIntegration;
use App\Actions\Integrations\DisconnectIntegration;
use App\Actions\Integrations\SyncIntegrations;
use App\Data\TeamIntegration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\StoreIntegrationRequest;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Data\ConnectableApp;
use App\Integrations\Exceptions\IntegrationAlreadyConnectedException;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IntegrationController extends Controller
{
    /**
     * Show the apps the team can connect, with their connection status.
     */
    public function index(Request $request, Team $current_team, IntegrationProvider $provider, SyncIntegrations $sync): Response
    {
        /** @var Collection<int, ConnectableApp> $catalog */
        $catalog = collect();
        $catalogLoaded = false;
        $providerError = null;

        if ($provider->isConfigured()) {
            try {
                $catalog = $provider->catalog();
                $catalogLoaded = true;

                $sync->handle($current_team);
            } catch (IntegrationProviderException $exception) {
                report($exception);

                $providerError = __(':provider could not be reached. Showing the last known connection status.', ['provider' => $provider->name()]);
            }
        }

        $integrations = $current_team->integrations()->with('connector')->get();

        $connected = $integrations
            ->where('provider', $provider->id())
            ->keyBy('provider_app_id');

        $catalog = $catalog->keyBy('id');

        $items = $catalog
            ->map(fn (ConnectableApp $app) => TeamIntegration::fromApp($app, $connected->get($app->id)))
            ->concat(
                $integrations
                    ->reject(fn (Integration $integration) => $integration->provider === $provider->id() && $catalog->has($integration->provider_app_id))
                    ->map(fn (Integration $integration) => TeamIntegration::fromIntegration(
                        $integration,
                        isAvailable: ! $catalogLoaded && $integration->provider === $provider->id(),
                    )),
            )
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return Inertia::render('integrations/index', [
            'team' => $request->user()->toUserTeam($current_team),
            'integrations' => $items,
            'permissions' => $request->user()->toTeamPermissions($current_team),
            'provider' => [
                'name' => $provider->name(),
                'dashboardUrl' => $provider->dashboardUrl(),
            ],
            'providerConfigured' => $provider->isConfigured(),
            'providerError' => $providerError,
        ]);
    }

    /**
     * Start connecting an app and send the user to the provider to authorize it.
     */
    public function store(StoreIntegrationRequest $request, Team $current_team, IntegrationProvider $provider, ConnectIntegration $connect): SymfonyResponse
    {
        Gate::authorize('manageIntegrations', $current_team);

        try {
            $app = $provider->app($request->validated('app_id'));

            if (! $app) {
                Inertia::flash('toast', ['type' => 'error', 'message' => __('The selected app is no longer available to connect.')]);

                return back();
            }

            $redirectUrl = $connect->handle($current_team, $request->user(), $app);
        } catch (IntegrationAlreadyConnectedException $exception) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __(':name is already connected. Disconnect it first to connect a different account.', ['name' => $exception->integration->name])]);

            return back();
        } catch (IntegrationProviderException $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => __(':provider could not be reached. Please try again.', ['provider' => $provider->name()])]);

            return back();
        }

        return Inertia::location($redirectUrl);
    }

    /**
     * Disconnect an app from the team.
     */
    public function destroy(Team $current_team, Integration $integration, IntegrationProvider $provider, DisconnectIntegration $disconnect): RedirectResponse
    {
        Gate::authorize('manageIntegrations', $current_team);

        try {
            $disconnect->handle($integration);
        } catch (IntegrationProviderException $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => __(':provider could not be reached. :name was not disconnected.', ['provider' => $provider->name(), 'name' => $integration->name])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name disconnected.', ['name' => $integration->name])]);

        return to_route('integrations.index', ['current_team' => $current_team->slug]);
    }
}
