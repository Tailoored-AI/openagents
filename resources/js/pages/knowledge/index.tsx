import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import CreateKnowledgePageModal from '@/components/create-knowledge-page-modal';
import Heading from '@/components/heading';
import KnowledgePageTree from '@/components/knowledge-page-tree';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/knowledge';
import type { KnowledgePageNode, Team, TeamPermissions } from '@/types';

type Props = {
    team: Pick<Team, 'id' | 'name' | 'slug'>;
    pages: KnowledgePageNode[];
    permissions: TeamPermissions;
};

export default function KnowledgeIndex({ team, pages }: Props) {
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <>
            <Head title="Knowledge" />

            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <div className="mb-8 flex items-start justify-between gap-4">
                    <Heading
                        title="Knowledge"
                        description={`Pages ${team.name} writes for its agents and workflows.`}
                    />
                    <Button
                        size="sm"
                        data-test="create-knowledge-page-button"
                        onClick={() => setCreateOpen(true)}
                    >
                        <PlusIcon />
                        New page
                    </Button>
                </div>

                {pages.length === 0 ? (
                    <div
                        className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm"
                        data-test="knowledge-empty-state"
                    >
                        No pages yet. Create the first page to start your team's
                        knowledge base.
                    </div>
                ) : (
                    <KnowledgePageTree team={team} pages={pages} />
                )}
            </div>

            <CreateKnowledgePageModal
                team={team}
                parentId={null}
                open={createOpen}
                onOpenChange={setCreateOpen}
            />
        </>
    );
}

KnowledgeIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Knowledge',
            href: index(props.team.slug),
        },
    ],
});
