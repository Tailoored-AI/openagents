import ConfirmDestructiveModal from '@/components/confirm-destructive-modal';
import { destroy } from '@/routes/knowledge';
import type { KnowledgePageDetail, Team } from '@/types';

type Props = {
    team: Pick<Team, 'slug'>;
    page: KnowledgePageDetail;
    title: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteKnowledgePageModal({
    team,
    page,
    title,
    open,
    onOpenChange,
}: Props) {
    const subpages =
        page.descendantCount === 1
            ? '1 subpage'
            : `${page.descendantCount} subpages`;

    return (
        <ConfirmDestructiveModal
            open={open}
            onOpenChange={onOpenChange}
            title={`Delete ${title}?`}
            description={
                page.descendantCount > 0
                    ? `This will also delete its ${subpages}. This cannot be undone.`
                    : 'This cannot be undone.'
            }
            confirmLabel="Delete"
            confirmTestId="delete-knowledge-page-confirm"
            action={destroy([team.slug, page.id])}
        />
    );
}
