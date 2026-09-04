import { Head, usePage } from '@inertiajs/react';
import { SearchIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import AgentCard from '@/components/agent-card';
import AgentDetailsModal from '@/components/agent-details-modal';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { agentCategories, agentLibrary } from '@/lib/agent-library';
import { index } from '@/routes/agents';
import type { AgentCategory, LibraryAgent } from '@/types';

function matchesSearch(agent: LibraryAgent, search: string): boolean {
    const haystack = [
        agent.name,
        agent.category,
        agent.summary,
        agent.description,
        agent.trigger,
        ...agent.apps,
    ]
        .join(' ')
        .toLowerCase();

    return haystack.includes(search);
}

export default function AgentsIndex() {
    const currentTeam = usePage().props.currentTeam;
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState<AgentCategory | null>(null);
    const [selectedAgent, setSelectedAgent] = useState<LibraryAgent | null>(
        null,
    );
    const [detailsOpen, setDetailsOpen] = useState(false);

    const visibleAgents = useMemo(() => {
        const term = search.trim().toLowerCase();

        return agentLibrary.filter(
            (agent) =>
                (category === null || agent.category === category) &&
                (term === '' || matchesSearch(agent, term)),
        );
    }, [category, search]);

    const showAgent = (agent: LibraryAgent) => {
        setSelectedAgent(agent);
        setDetailsOpen(true);
    };

    return (
        <>
            <Head title="Agents" />

            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <Heading
                    title="Agents"
                    description={`Ready-made agents ${currentTeam?.name ?? 'your team'} can put to work on repetitive tasks.`}
                />

                <div className="space-y-6">
                    <div className="space-y-4">
                        <div className="relative max-w-sm">
                            <SearchIcon className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={search}
                                data-test="agent-search"
                                placeholder="Search agents, tasks, or apps"
                                aria-label="Search agents"
                                className="pl-9"
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant={
                                    category === null ? 'secondary' : 'ghost'
                                }
                                aria-pressed={category === null}
                                onClick={() => setCategory(null)}
                            >
                                All
                            </Button>

                            {agentCategories.map((agentCategory) => (
                                <Button
                                    key={agentCategory}
                                    size="sm"
                                    variant={
                                        category === agentCategory
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    aria-pressed={category === agentCategory}
                                    onClick={() => setCategory(agentCategory)}
                                >
                                    {agentCategory}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {visibleAgents.length === 0 ? (
                        <div
                            className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm"
                            data-test="agents-empty-state"
                        >
                            No agents match that search. Try a different task or
                            app name.
                        </div>
                    ) : (
                        <>
                            <p className="text-muted-foreground text-sm">
                                Showing {visibleAgents.length} of{' '}
                                {agentLibrary.length} agents
                            </p>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {visibleAgents.map((agent) => (
                                    <AgentCard
                                        key={agent.slug}
                                        agent={agent}
                                        onSelect={showAgent}
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>

            <AgentDetailsModal
                team={currentTeam}
                agent={selectedAgent}
                open={detailsOpen}
                onOpenChange={setDetailsOpen}
            />
        </>
    );
}

AgentsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Agents',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
