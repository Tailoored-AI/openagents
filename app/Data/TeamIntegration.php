<?php

namespace App\Data;

use App\Integrations\Data\ConnectableApp;
use App\Models\Integration;

/**
 * An app as shown on the team's integrations page, connected or not.
 */
readonly class TeamIntegration
{
    public function __construct(
        public ?int $id,
        public string $appId,
        public string $appSlug,
        public string $name,
        public ?string $description,
        public ?string $logo,
        public ?string $authScheme,
        public bool $isAvailable,
        public ?string $status,
        public ?string $statusLabel,
        public ?string $statusReason,
        public ?string $connectedAt,
        public ?string $connectedBy,
    ) {
        //
    }

    /**
     * Build the entry for an app in the provider's catalog, with the team's connection if it has one.
     */
    public static function fromApp(ConnectableApp $app, ?Integration $integration): self
    {
        return new self(
            id: $integration?->id,
            appId: $app->id,
            appSlug: $app->slug,
            name: $app->name,
            description: $app->description,
            logo: $app->logo,
            authScheme: $app->authScheme,
            isAvailable: true,
            status: $integration?->status->value,
            statusLabel: $integration?->status->label(),
            statusReason: $integration?->status_reason,
            connectedAt: $integration?->connected_at?->toISOString(),
            connectedBy: $integration?->connector?->name,
        );
    }

    /**
     * Build the entry for a stored connection whose app is not in the provider's catalog.
     *
     * The app is unavailable when the catalog was loaded and no longer lists
     * it, or when a former provider brokered the connection; while the catalog
     * cannot be loaded the app is assumed to still be available.
     */
    public static function fromIntegration(Integration $integration, bool $isAvailable): self
    {
        return new self(
            id: $integration->id,
            appId: $integration->provider_app_id,
            appSlug: $integration->app_slug,
            name: $integration->name,
            description: null,
            logo: $integration->logo,
            authScheme: null,
            isAvailable: $isAvailable,
            status: $integration->status->value,
            statusLabel: $integration->status->label(),
            statusReason: $integration->status_reason,
            connectedAt: $integration->connected_at?->toISOString(),
            connectedBy: $integration->connector?->name,
        );
    }
}
