import { router } from '@inertiajs/react';
import { AlertCircleIcon, CheckIcon, LoaderCircleIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AutosaveStatus, KnowledgePageConflict } from '@/types';

type Props = {
    status: AutosaveStatus;
    conflict: KnowledgePageConflict | null;
    onRetry: () => void;
};

export default function AutosaveStatusIndicator({
    status,
    conflict,
    onRetry,
}: Props) {
    if (status === 'conflict') {
        return (
            <div
                className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400"
                data-test="autosave-status"
                data-status={status}
            >
                <AlertCircleIcon className="size-4" />
                <span>{conflict?.message}</span>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.visit(window.location.href, {
                            preserveState: false,
                        })
                    }
                >
                    Reload
                </Button>
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div
                className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400"
                data-test="autosave-status"
                data-status={status}
            >
                <AlertCircleIcon className="size-4" />
                <span>Could not save.</span>
                <Button size="sm" variant="outline" onClick={onRetry}>
                    Retry
                </Button>
            </div>
        );
    }

    const label = {
        idle: null,
        dirty: 'Unsaved changes',
        saving: 'Saving…',
        saved: 'Saved',
    }[status];

    if (!label) {
        return null;
    }

    return (
        <div
            className="text-muted-foreground flex items-center gap-1.5 text-sm"
            data-test="autosave-status"
            data-status={status}
        >
            {status === 'saving' ? (
                <LoaderCircleIcon className="size-4 animate-spin" />
            ) : status === 'saved' ? (
                <CheckIcon className="size-4" />
            ) : null}
            <span>{label}</span>
        </div>
    );
}
