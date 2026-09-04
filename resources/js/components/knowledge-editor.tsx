'use no memo';

import '@blocknote/core/fonts/inter.css';
import '@blocknote/shadcn/style.css';

import type { Block, PartialBlock } from '@blocknote/core';
import { useCreateBlockNote } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/shadcn';
import { useAppearance } from '@/hooks/use-appearance';

type Props = {
    initialContent?: PartialBlock[];
    onChange: (blocks: Block[]) => void;
};

/**
 * The only component that loads BlockNote at runtime. It must never render on
 * the server: mount it through ClientOnlyKnowledgeEditor.
 */
export default function KnowledgeEditor({ initialContent, onChange }: Props) {
    const { resolvedAppearance } = useAppearance();
    const editor = useCreateBlockNote({ initialContent });

    return (
        <BlockNoteView
            editor={editor}
            theme={resolvedAppearance}
            onChange={() => onChange(editor.document)}
            data-test="knowledge-editor"
        />
    );
}
