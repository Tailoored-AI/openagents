import type { Block, PartialBlock } from '@blocknote/core';

export type KnowledgePageNode = {
    id: number;
    title: string | null;
    updatedAt: string;
    children: KnowledgePageNode[];
};

export type KnowledgePageSummary = {
    id: number;
    title: string | null;
};

export type KnowledgePageDetail = {
    id: number;
    title: string | null;
    content: PartialBlock[] | null;
    version: number;
    updatedAt: string;
    parentId: number | null;
    createdBy: { id: number; name: string } | null;
    updatedBy: string | null;
    canDelete: boolean;
    descendantCount: number;
};

export type KnowledgePageSavePayload = {
    title?: string | null;
    content?: Block[] | null;
};

export type KnowledgePageSaveResponse = {
    version: number;
    updatedAt: string;
};

export type KnowledgePageConflict = {
    message: string;
    version: number;
    updatedAt: string | null;
    updatedBy: string | null;
};

export type AutosaveStatus =
    | 'idle'
    | 'dirty'
    | 'saving'
    | 'saved'
    | 'error'
    | 'conflict';
