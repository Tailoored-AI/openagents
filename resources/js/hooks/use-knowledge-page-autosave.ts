import { router, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type {
    AutosaveStatus,
    KnowledgePageConflict,
    KnowledgePageSavePayload,
    KnowledgePageSaveResponse,
} from '@/types';

type Options = {
    url: string;
    initialVersion: number;
    delay?: number;
};

type SaveRequest = KnowledgePageSavePayload & { version: number };

export type UseKnowledgePageAutosaveReturn = {
    status: AutosaveStatus;
    conflict: KnowledgePageConflict | null;
    schedule: (patch: KnowledgePageSavePayload) => void;
    flush: () => void;
};

/**
 * Debounces edits to a page and saves them one request at a time.
 *
 * Pending changes survive a failed save so they can be retried, and a 409
 * stops saving until the page is reloaded.
 */
export function useKnowledgePageAutosave({
    url,
    initialVersion,
    delay = 800,
}: Options): UseKnowledgePageAutosaveReturn {
    const [status, setStatus] = useState<AutosaveStatus>('idle');
    const [conflict, setConflict] = useState<KnowledgePageConflict | null>(
        null,
    );

    const http = useHttp<SaveRequest, KnowledgePageSaveResponse>({
        version: initialVersion,
    });

    const pendingRef = useRef<KnowledgePageSavePayload>({});
    const sendingRef = useRef<KnowledgePageSavePayload>({});
    const versionRef = useRef(initialVersion);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const inFlightRef = useRef(false);
    const queuedRef = useRef(false);
    const stoppedRef = useRef(false);
    const flushRef = useRef<() => void>(() => {});

    useEffect(() => {
        // The transform runs when the request is sent, so it reads the
        // changes set aside for that request rather than the ones still
        // accumulating in pendingRef.
        http.transform((data) => ({
            ...data,
            ...sendingRef.current,
            version: versionRef.current,
        }));
    });

    const flush = () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        if (
            stoppedRef.current ||
            Object.keys(pendingRef.current).length === 0
        ) {
            return;
        }

        if (inFlightRef.current) {
            queuedRef.current = true;

            return;
        }

        const sent = pendingRef.current;
        pendingRef.current = {};
        sendingRef.current = sent;
        inFlightRef.current = true;
        setStatus('saving');

        const restore = () => {
            pendingRef.current = { ...sent, ...pendingRef.current };
        };

        http.patch(url, {
            onSuccess: (response) => {
                versionRef.current = response.version;
                setStatus(queuedRef.current ? 'dirty' : 'saved');
            },
            onError: () => {
                restore();
                setStatus('error');
            },
            onHttpException: (response) => {
                restore();

                if (response.status === 409) {
                    stoppedRef.current = true;
                    setConflict(parseConflict(response.data));
                    setStatus('conflict');
                } else {
                    setStatus('error');
                }
            },
            onNetworkError: () => {
                restore();
                setStatus('error');
            },
            onFinish: () => {
                sendingRef.current = {};
                inFlightRef.current = false;

                if (queuedRef.current) {
                    queuedRef.current = false;
                    flushRef.current();
                }
            },
        }).catch(() => {
            // Every failure is already reflected in the status by the
            // callbacks above; useHttp rethrows regardless.
        });
    };

    flushRef.current = flush;

    const schedule = (patch: KnowledgePageSavePayload) => {
        if (stoppedRef.current) {
            return;
        }

        pendingRef.current = { ...pendingRef.current, ...patch };
        setStatus('dirty');

        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }

        timerRef.current = setTimeout(() => flushRef.current(), delay);
    };

    useEffect(() => {
        const unsubscribe = router.on('before', () => {
            flushRef.current();
        });

        const beforeUnload = (event: BeforeUnloadEvent) => {
            flushRef.current();

            if (
                inFlightRef.current ||
                Object.keys(pendingRef.current).length > 0
            ) {
                event.preventDefault();
            }
        };

        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            unsubscribe();
            window.removeEventListener('beforeunload', beforeUnload);
            flushRef.current();
        };
    }, []);

    return { status, conflict, schedule, flush };
}

function parseConflict(data: string): KnowledgePageConflict {
    try {
        const parsed = JSON.parse(data) as Partial<KnowledgePageConflict>;

        return {
            message:
                parsed.message ??
                'This page was changed elsewhere. Reload to see the latest version.',
            version: parsed.version ?? 0,
            updatedAt: parsed.updatedAt ?? null,
            updatedBy: parsed.updatedBy ?? null,
        };
    } catch {
        return {
            message:
                'This page was changed elsewhere. Reload to see the latest version.',
            version: 0,
            updatedAt: null,
            updatedBy: null,
        };
    }
}
