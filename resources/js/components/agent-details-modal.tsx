import { Link } from '@inertiajs/react';
import { PlugIcon, ZapIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as integrations } from '@/routes/integrations';
import type { LibraryAgent, Team } from '@/types';

type Props = {
    team: Pick<Team, 'slug'> | null;
    agent: LibraryAgent | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function AgentDetailsModal({
    team,
    agent,
    open,
    onOpenChange,
}: Props) {
    if (!agent) {
        return null;
    }

    const Icon = agent.icon;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent data-test="agent-details-modal">
                <DialogHeader>
                    <div className="flex items-start gap-3">
                        <div className="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg">
                            <Icon className="size-5" />
                        </div>
                        <div className="min-w-0 flex-1 space-y-1 text-left">
                            <DialogTitle>{agent.name}</DialogTitle>
                            <Badge variant="outline">{agent.category}</Badge>
                        </div>
                    </div>
                    <DialogDescription className="pt-2 text-left">
                        {agent.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <p className="text-sm font-medium">How it runs</p>
                        <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                            <ZapIcon className="size-3.5 shrink-0" />
                            {agent.trigger}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <p className="text-sm font-medium">What it does</p>
                        <ol className="text-muted-foreground list-decimal space-y-1 pl-5 text-sm">
                            {agent.steps.map((step) => (
                                <li key={step}>{step}</li>
                            ))}
                        </ol>
                    </div>

                    <div className="space-y-2">
                        <p className="text-sm font-medium">Apps it needs</p>
                        <div className="flex flex-wrap gap-1.5">
                            {agent.apps.map((app) => (
                                <Badge
                                    key={app}
                                    variant="outline"
                                    className="font-normal"
                                >
                                    {app}
                                </Badge>
                            ))}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    {team ? (
                        <Button variant="outline" asChild>
                            <Link href={integrations(team.slug)}>
                                <PlugIcon />
                                Review integrations
                            </Link>
                        </Button>
                    ) : null}

                    <Button disabled data-test="agent-use-button">
                        Use this agent
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
