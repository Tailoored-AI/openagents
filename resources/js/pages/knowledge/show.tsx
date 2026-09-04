import { Head, Link } from '@inertiajs/react';
import { FileTextIcon, PlusIcon, Trash2Icon } from 'lucide-react';
import { useState } from 'react';
import AutosaveStatusIndicator from '@/components/autosave-status';
import ClientOnlyKnowledgeEditor from '@/components/client-only-knowledge-editor';
import CreateKnowledgePageModal from '@/components/create-knowledge-page-modal';
import DeleteKnowledgePageModal from '@/components/delete-knowledge-page-modal';
import { pageTitle } from '@/components/knowledge-page-tree';
import { Button } from '@/components/ui/button';
import { useKnowledgePageAutosave } from '@/hooks/use-knowledge-page-autosave';
import { index, show, update } from '@/routes/knowledge';
import type { KnowledgePageDetail, KnowledgePageSummary, Team } from '@/types';

type Props = {
    team: Pick<Team, 'id' | 'name' | 'slug'>;
    page: KnowledgePageDetail;
    ancestors: KnowledgePageSummary[];
    children: KnowledgePageSummary[];
};

export default function KnowledgeShow({ team, page, children }: Props) {
    const [title, setTitle] = useState(page.title ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const autosave = useKnowledgePageAutosave({
        url: update.url([team.slug, page.id]),
        initialVersion: page.version,
    });

    const displayTitle = pageTitle(title);

    return (
        <>
            <Head title={displayTitle} />

            <div className="flex h-full flex-1 flex-col p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <AutosaveStatusIndicator
                        status={autosave.status}
                        conflict={autosave.conflict}
                        onRetry={autosave.flush}
                    />

                    <div className="ml-auto flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            data-test="create-knowledge-subpage-button"
                            onClick={() => setCreateOpen(true)}
                        >
                            <PlusIcon />
                            New subpage
                        </Button>

                        {page.canDelete ? (
                            <Button
                                size="sm"
                                variant="outline"
                                data-test="delete-knowledge-page-button"
                                onClick={() => setDeleteOpen(true)}
                            >
                                <Trash2Icon />
                                Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="mx-auto w-full max-w-3xl flex-1">
                    <input
                        type="text"
                        value={title}
                        placeholder="Untitled"
                        aria-label="Page title"
                        data-test="knowledge-page-title"
                        className="placeholder:text-muted-foreground/60 mb-4 w-full bg-transparent text-3xl font-semibold tracking-tight outline-none"
                        onChange={(event) => {
                            setTitle(event.target.value);
                            autosave.schedule({
                                title:
                                    event.target.value.trim() === ''
                                        ? null
                                        : event.target.value,
                            });
                        }}
                    />

                    <div key={page.id}>
                        <ClientOnlyKnowledgeEditor
                            initialContent={
                                page.content?.length ? page.content : undefined
                            }
                            onChange={(blocks) =>
                                autosave.schedule({ content: blocks })
                            }
                        />
                    </div>

                    {children.length > 0 ? (
                        <section className="mt-10 border-t pt-6">
                            <h3 className="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase">
                                Subpages
                            </h3>
                            <ul className="space-y-1">
                                {children.map((child) => (
                                    <li key={child.id}>
                                        <Link
                                            href={show([team.slug, child.id])}
                                            prefetch
                                            className="hover:bg-muted flex items-center gap-2 rounded-md px-2 py-1 text-sm"
                                            data-test="knowledge-subpage-link"
                                        >
                                            <FileTextIcon className="text-muted-foreground size-4" />
                                            {pageTitle(child.title)}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}
                </div>
            </div>

            <CreateKnowledgePageModal
                team={team}
                parentId={page.id}
                parentTitle={displayTitle}
                open={createOpen}
                onOpenChange={setCreateOpen}
            />

            <DeleteKnowledgePageModal
                team={team}
                page={page}
                title={displayTitle}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

KnowledgeShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Knowledge', href: index(props.team.slug) },
        ...props.ancestors.map((ancestor) => ({
            title: pageTitle(ancestor.title),
            href: show([props.team.slug, ancestor.id]),
        })),
        {
            title: pageTitle(props.page.title),
            href: show([props.team.slug, props.page.id]),
        },
    ],
});
