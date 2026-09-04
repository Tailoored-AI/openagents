<?php

namespace App\Providers;

use App\Http\Clients\ComposioClient;
use App\Integrations\Composio\ComposioProvider;
use App\Integrations\Contracts\IntegrationProvider;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register the integration provider that brokers team connections.
     *
     * Swap the bound implementation to move to another integrations service.
     */
    public function register(): void
    {
        $this->app->singleton(ComposioClient::class, fn () => new ComposioClient(
            apiKey: config('services.composio.key'),
            baseUrl: config('services.composio.url'),
        ));

        $this->app->bind(IntegrationProvider::class, ComposioProvider::class);
    }
}
