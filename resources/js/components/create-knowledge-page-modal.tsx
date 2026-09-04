import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/knowledge';
import type { Team } from '@/types';

type Props = {
    team: Pick<Team, 'slug'>;
    parentId: number | null;
    parentTitle?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CreateKnowledgePageModal({
    team,
    parentId,
    parentTitle,
    open,
    onOpenChange,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    action={store(team.slug)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {parentId ? 'New subpage' : 'New page'}
                                </DialogTitle>
                                <DialogDescription>
                                    {parentId
                                        ? `The page will be nested under ${parentTitle ?? 'this page'}.`
                                        : 'Give the page a title, or leave it blank and name it later.'}
                                </DialogDescription>
                            </DialogHeader>

                            {parentId ? (
                                <input
                                    type="hidden"
                                    name="parent_id"
                                    value={parentId}
                                />
                            ) : null}

                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    data-test="create-knowledge-page-title"
                                    placeholder="Untitled"
                                    autoFocus
                                />
                                <InputError message={errors.title} />
                                <InputError message={errors.parent_id} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="create-knowledge-page-submit"
                                    disabled={processing}
                                >
                                    Create page
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
