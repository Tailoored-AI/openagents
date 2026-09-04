import type { LucideIcon } from 'lucide-react';

export type AgentCategory =
    | 'Sales'
    | 'Support'
    | 'Marketing'
    | 'Engineering'
    | 'Finance'
    | 'Operations'
    | 'People';

export type LibraryAgent = {
    slug: string;
    name: string;
    category: AgentCategory;
    icon: LucideIcon;
    summary: string;
    description: string;
    trigger: string;
    apps: string[];
    steps: string[];
    isFeatured: boolean;
};
