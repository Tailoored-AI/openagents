import { Link } from '@inertiajs/react';
import { ChevronRightIcon, FileTextIcon } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { show } from '@/routes/knowledge';
import type { KnowledgePageNode, Team } from '@/types';

type Props = {
    team: Pick<Team, 'slug'>;
    pages: KnowledgePageNode[];
    depth?: number;
};

export function pageTitle(title: string | null): string {
    return title && title.trim() !== '' ? title : 'Untitled';
}

function TreeRow({
    team,
    page,
    depth,
}: {
    team: Pick<Team, 'slug'>;
    page: KnowledgePageNode;
    depth: number;
}) {
    const [open, setOpen] = useState(true);
    const hasChildren = page.children.length > 0;

    return (
        <li>
            <Collapsible open={open} onOpenChange={setOpen}>
                <div
                    className="hover:bg-muted flex items-center gap-1 rounded-md py-1 pr-2"
                    style={{ paddingLeft: `${depth * 1.25}rem` }}
                    data-test="knowledge-page-row"
                >
                    {hasChildren ? (
                        <CollapsibleTrigger
                            className="text-muted-foreground hover:text-foreground flex size-6 shrink-0 items-center justify-center rounded"
                            aria-label={open ? 'Collapse' : 'Expand'}
                        >
                            <ChevronRightIcon
                                className={cn(
                                    'size-4 transition-transform',
                                    open && 'rotate-90',
                                )}
                            />
                        </CollapsibleTrigger>
                    ) : (
                        <span className="size-6 shrink-0" />
                    )}

                    <Link
                        href={show([team.slug, page.id])}
                        prefetch
                        className="flex min-w-0 flex-1 items-center gap-2 text-sm"
                    >
                        <FileTextIcon className="text-muted-foreground size-4 shrink-0" />
                        <span className="truncate">
                            {pageTitle(page.title)}
                        </span>
                    </Link>
                </div>

                {hasChildren ? (
                    <CollapsibleContent>
                        <KnowledgePageTree
                            team={team}
                            pages={page.children}
                            depth={depth + 1}
                        />
                    </CollapsibleContent>
                ) : null}
            </Collapsible>
        </li>
    );
}

export default function KnowledgePageTree({ team, pages, depth = 0 }: Props) {
    return (
        <ul className="space-y-0.5">
            {pages.map((page) => (
                <TreeRow key={page.id} team={team} page={page} depth={depth} />
            ))}
        </ul>
    );
}
