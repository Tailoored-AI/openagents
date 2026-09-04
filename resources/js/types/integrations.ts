export type IntegrationStatus =
    | 'initiated'
    | 'active'
    | 'expired'
    | 'failed'
    | 'inactive';

export type IntegrationProviderInfo = {
    name: string;
    dashboardUrl: string | null;
};

export type TeamIntegration = {
    id: number | null;
    appId: string;
    appSlug: string;
    name: string;
    description: string | null;
    logo: string | null;
    authScheme: string | null;
    isAvailable: boolean;
    status: IntegrationStatus | null;
    statusLabel: string | null;
    statusReason: string | null;
    connectedAt: string | null;
    connectedBy: string | null;
};
