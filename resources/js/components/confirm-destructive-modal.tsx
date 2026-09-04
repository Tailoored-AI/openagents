import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    description: ReactNode;
    confirmLabel: string;
    confirmTestId: string;
    /**
     * The visit made when the user confirms; null leaves confirming a no-op.
     */
    action: Parameters<typeof router.visit>[0] | null;
};

/**
 * Asks the user to confirm an action that cannot be undone before visiting it.
 */
export default function ConfirmDestructiveModal({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    confirmTestId,
    action,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!action) {
            return;
        }

        router.visit(action, {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test={confirmTestId}
                        disabled={processing}
                        onClick={confirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
