import { Head, router } from '@inertiajs/react';
import { AlertCircleIcon } from 'lucide-react';
import { useState } from 'react';
import DisconnectIntegrationModal from '@/components/disconnect-integration-modal';
import Heading from '@/components/heading';
import IntegrationCard from '@/components/integration-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { index, store } from '@/routes/integrations';
import type {
    IntegrationProviderInfo,
    Team,
    TeamIntegration,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Pick<Team, 'id' | 'name' | 'slug'>;
    integrations: TeamIntegration[];
    permissions: TeamPermissions;
    provider: IntegrationProviderInfo;
    providerConfigured: boolean;
    providerError: string | null;
};

export default function IntegrationsIndex({
    team,
    integrations,
    permissions,
    provider,
    providerConfigured,
    providerError,
}: Props) {
    const [connectingAppId, setConnectingAppId] = useState<string | null>(null);
    const [disconnectDialogOpen, setDisconnectDialogOpen] = useState(false);
    const [integrationToDisconnect, setIntegrationToDisconnect] =
        useState<TeamIntegration | null>(null);

    const connect = (integration: TeamIntegration) => {
        router.visit(store(team.slug), {
            data: { app_id: integration.appId },
            onStart: () => setConnectingAppId(integration.appId),
            onFinish: () => setConnectingAppId(null),
        });
    };

    const confirmDisconnect = (integration: TeamIntegration) => {
        setIntegrationToDisconnect(integration);
        setDisconnectDialogOpen(true);
    };

    const showEmptyState =
        providerConfigured && !providerError && integrations.length === 0;

    return (
        <>
            <Head title="Integrations" />

            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <Heading
                    title="Integrations"
                    description={`Connect the apps ${team.name} can use in its agents and workflows.`}
                />

                <div className="space-y-6">
                    {!providerConfigured ? (
                        <Alert data-test="provider-not-configured">
                            <AlertCircleIcon />
                            <AlertTitle>
                                {provider.name} is not configured
                            </AlertTitle>
                            <AlertDescription>
                                Add the {provider.name} API key to your
                                environment to let teams connect their apps.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {providerError ? (
                        <Alert variant="destructive" data-test="provider-error">
                            <AlertCircleIcon />
                            <AlertTitle>
                                {provider.name} is unavailable
                            </AlertTitle>
                            <AlertDescription>{providerError}</AlertDescription>
                        </Alert>
                    ) : null}

                    {showEmptyState ? (
                        <div className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                            No apps are available to connect yet. Enable an app
                            in your{' '}
                            {provider.dashboardUrl ? (
                                <a
                                    href={provider.dashboardUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-foreground underline underline-offset-4"
                                >
                                    {provider.name} dashboard
                                </a>
                            ) : (
                                <>{provider.name} dashboard</>
                            )}
                            .
                        </div>
                    ) : null}

                    {integrations.length > 0 ? (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {integrations.map((integration) => (
                                <IntegrationCard
                                    key={integration.appId}
                                    integration={integration}
                                    canManage={
                                        permissions.canManageIntegrations
                                    }
                                    disabled={!providerConfigured}
                                    connecting={
                                        connectingAppId === integration.appId
                                    }
                                    onConnect={connect}
                                    onDisconnect={confirmDisconnect}
                                />
                            ))}
                        </div>
                    ) : null}
                </div>
            </div>

            <DisconnectIntegrationModal
                team={team}
                integration={integrationToDisconnect}
                open={disconnectDialogOpen}
                onOpenChange={setDisconnectDialogOpen}
            />
        </>
    );
}

IntegrationsIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Integrations',
            href: index(props.team.slug),
        },
    ],
});
