import ConfirmDestructiveModal from '@/components/confirm-destructive-modal';
import { destroy } from '@/routes/integrations';
import type { Team, TeamIntegration } from '@/types';

type Props = {
    team: Pick<Team, 'slug'>;
    integration: TeamIntegration | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DisconnectIntegrationModal({
    team,
    integration,
    open,
    onOpenChange,
}: Props) {
    return (
        <ConfirmDestructiveModal
            open={open}
            onOpenChange={onOpenChange}
            title={`Disconnect ${integration?.name}?`}
            description={
                <>
                    Your team's agents will no longer be able to use{' '}
                    <strong>{integration?.name}</strong>. You can connect it
                    again at any time.
                </>
            }
            confirmLabel="Disconnect"
            confirmTestId="disconnect-integration-confirm"
            action={
                integration && integration.id !== null
                    ? destroy([team.slug, integration.id])
                    : null
            }
        />
    );
}
