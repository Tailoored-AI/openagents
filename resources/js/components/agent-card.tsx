import { ZapIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { LibraryAgent } from '@/types';

type Props = {
    agent: LibraryAgent;
    onSelect: (agent: LibraryAgent) => void;
};

export default function AgentCard({ agent, onSelect }: Props) {
    const Icon = agent.icon;

    return (
        <button
            type="button"
            data-test="agent-card"
            onClick={() => onSelect(agent)}
            className="hover:border-ring focus-visible:border-ring focus-visible:ring-ring/50 flex h-full flex-col gap-4 rounded-xl border p-4 text-left transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
        >
            <div className="flex items-start gap-3">
                <div className="bg-muted text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg">
                    <Icon className="size-5" />
                </div>

                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="truncate font-medium">
                            {agent.name}
                        </span>
                        {agent.isFeatured ? (
                            <Badge variant="secondary">Popular</Badge>
                        ) : null}
                    </div>
                    <Badge variant="outline">{agent.category}</Badge>
                </div>
            </div>

            <p className="text-muted-foreground line-clamp-3 text-sm">
                {agent.summary}
            </p>

            <div className="mt-auto space-y-3">
                <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                    <ZapIcon className="size-3.5 shrink-0" />
                    <span className="truncate">{agent.trigger}</span>
                </p>

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
        </button>
    );
}
