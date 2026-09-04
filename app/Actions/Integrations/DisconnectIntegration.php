<?php

namespace App\Actions\Integrations;

use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Exceptions\IntegrationProviderException;
use App\Models\Integration;

class DisconnectIntegration
{
    public function __construct(protected IntegrationProvider $provider)
    {
        //
    }

    /**
     * Remove the connection from the provider and forget it locally.
     *
     * @throws IntegrationProviderException
     */
    public function handle(Integration $integration): void
    {
        if ($integration->provider === $this->provider->id()) {
            $this->provider->disconnect($integration->provider_connection_id);
        }

        $integration->delete();
    }
}
