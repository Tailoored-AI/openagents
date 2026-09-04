import type { ComponentProps } from 'react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

const KnowledgeEditor = lazy(() => import('@/components/knowledge-editor'));

type Props = ComponentProps<typeof KnowledgeEditor>;

function EditorSkeleton() {
    return (
        <div className="space-y-3 py-2" data-test="knowledge-editor-skeleton">
            <Skeleton className="h-5 w-3/4" />
            <Skeleton className="h-5 w-full" />
            <Skeleton className="h-5 w-5/6" />
            <Skeleton className="h-5 w-2/3" />
        </div>
    );
}

/**
 * Renders the editor on the client only. Server-side rendering and the first
 * client paint both show the skeleton, so hydration stays consistent and the
 * editor bundle never runs on the server.
 */
export default function ClientOnlyKnowledgeEditor(props: Props) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    if (!mounted) {
        return <EditorSkeleton />;
    }

    return (
        <Suspense fallback={<EditorSkeleton />}>
            <KnowledgeEditor {...props} />
        </Suspense>
    );
}
