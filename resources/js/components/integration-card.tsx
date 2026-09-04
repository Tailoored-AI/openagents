import { Plug, Unplug } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { IntegrationStatus, TeamIntegration } from '@/types';

type Props = {
    integration: TeamIntegration;
    canManage: boolean;
    disabled: boolean;
    connecting: boolean;
    onConnect: (integration: TeamIntegration) => void;
    onDisconnect: (integration: TeamIntegration) => void;
};

const authSchemeLabels: Record<string, string> = {
    OAUTH2: 'OAuth 2.0',
    OAUTH1: 'OAuth 1.0',
    API_KEY: 'API key',
    BEARER_TOKEN: 'Bearer token',
    BASIC: 'Basic auth',
    NO_AUTH: 'No auth',
};

const statusBadgeStyles: Record<IntegrationStatus, string> = {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400',
    initiated:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400',
    expired:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400',
    failed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400',
    inactive: 'text-muted-foreground',
};

function authSchemeLabel(authScheme: string | null): string | null {
    if (!authScheme) {
        return null;
    }

    return (
        authSchemeLabels[authScheme] ??
        authScheme.toLowerCase().replaceAll('_', ' ')
    );
}

function connectLabel(status: IntegrationStatus | null): string {
    switch (status) {
        case null:
            return 'Connect';
        case 'initiated':
            return 'Continue';
        default:
            return 'Reconnect';
    }
}

function StatusBadge({
    status,
    label,
}: {
    status: IntegrationStatus | null;
    label: string | null;
}) {
    if (!status) {
        return <Badge variant="secondary">Not connected</Badge>;
    }

    return (
        <Badge variant="outline" className={cn(statusBadgeStyles[status])}>
            {label}
        </Badge>
    );
}

export default function IntegrationCard({
    integration,
    canManage,
    disabled,
    connecting,
    onConnect,
    onDisconnect,
}: Props) {
    const canConnect =
        integration.isAvailable && integration.status !== 'active';
    const canDisconnect = integration.id !== null;
    const connectedAt = integration.connectedAt
        ? new Date(integration.connectedAt).toLocaleDateString(undefined, {
              dateStyle: 'medium',
          })
        : null;
    const details = [
        authSchemeLabel(integration.authScheme),
        integration.appSlug,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div
            data-test="integration-card"
            className="flex flex-col gap-4 rounded-xl border p-4"
        >
            <div className="flex items-start gap-3">
                {integration.logo ? (
                    <img
                        src={integration.logo}
                        alt=""
                        className="size-10 shrink-0 rounded-lg border bg-white object-contain p-1"
                    />
                ) : (
                    <div className="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg text-sm font-semibold uppercase">
                        {integration.name.charAt(0)}
                    </div>
                )}

                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="truncate font-medium">
                            {integration.name}
                        </span>
                        <StatusBadge
                            status={integration.status}
                            label={integration.statusLabel}
                        />
                    </div>
                    {details ? (
                        <p className="text-muted-foreground text-sm">
                            {details}
                        </p>
                    ) : null}
                </div>
            </div>

            {integration.description ? (
                <p className="text-muted-foreground line-clamp-2 text-sm">
                    {integration.description}
                </p>
            ) : null}

            {integration.status === 'active' && integration.connectedBy ? (
                <p className="text-muted-foreground text-sm">
                    Connected by {integration.connectedBy}
                    {connectedAt ? ` on ${connectedAt}` : ''}
                </p>
            ) : null}

            {integration.statusReason ? (
                <p className="text-sm text-red-600 dark:text-red-400">
                    {integration.statusReason}
                </p>
            ) : null}

            {!integration.isAvailable ? (
                <p className="text-muted-foreground text-sm">
                    This app is no longer available to connect.
                </p>
            ) : null}

            {canManage ? (
                <div className="mt-auto flex flex-wrap items-center gap-2">
                    {canConnect ? (
                        <Button
                            size="sm"
                            data-test="integration-connect-button"
                            disabled={disabled || connecting}
                            onClick={() => onConnect(integration)}
                        >
                            <Plug />
                            {connecting
                                ? 'Redirecting…'
                                : connectLabel(integration.status)}
                        </Button>
                    ) : null}

                    {canDisconnect ? (
                        <Button
                            size="sm"
                            variant="outline"
                            data-test="integration-disconnect-button"
                            disabled={disabled}
                            onClick={() => onDisconnect(integration)}
                        >
                            <Unplug />
                            Disconnect
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
