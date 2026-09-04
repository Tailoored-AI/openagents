import {
    BookMarked,
    CalendarCheck,
    ClipboardList,
    FileSearch,
    GitPullRequestArrow,
    Inbox,
    LifeBuoy,
    Megaphone,
    Radar,
    ReceiptText,
    Send,
    Sparkles,
    UserRoundSearch,
    Users,
} from 'lucide-react';
import type { AgentCategory, LibraryAgent } from '@/types';

export const agentCategories: AgentCategory[] = [
    'Sales',
    'Support',
    'Marketing',
    'Engineering',
    'Finance',
    'Operations',
    'People',
];

/**
 * The catalogue of ready-made agents a team can browse and put to work.
 */
export const agentLibrary: LibraryAgent[] = [
    {
        slug: 'inbox-triage',
        name: 'Inbox Triage',
        category: 'Support',
        icon: Inbox,
        summary:
            'Sorts incoming mail, labels what matters, and routes the rest to the right person.',
        description:
            'Reads every message that lands in a shared inbox, decides whether it is a bug report, a sales enquiry, or noise, and routes it to the owning channel with a two-line summary so nobody opens a thread twice.',
        trigger: 'New message in a shared inbox',
        apps: ['Gmail', 'Slack', 'Linear'],
        steps: [
            'Read the new message and its thread history',
            'Classify it against your team’s routing rules',
            'Apply labels and archive anything already handled',
            'Post a summary in the owning channel',
        ],
        isFeatured: true,
    },
    {
        slug: 'meeting-notetaker',
        name: 'Meeting Notetaker',
        category: 'Operations',
        icon: CalendarCheck,
        summary:
            'Turns each meeting transcript into notes, decisions, and assigned follow-ups.',
        description:
            'Picks up the transcript after a call ends, writes the notes the way your team writes them, pulls out every decision and action item, and files the page where the rest of the team already looks.',
        trigger: 'Calendar event ends',
        apps: ['Google Calendar', 'Knowledge', 'Slack'],
        steps: [
            'Collect the transcript and attendee list',
            'Draft notes, decisions, and open questions',
            'Create a task for every follow-up with an owner',
            'Publish the page and share it with attendees',
        ],
        isFeatured: true,
    },
    {
        slug: 'lead-researcher',
        name: 'Lead Researcher',
        category: 'Sales',
        icon: UserRoundSearch,
        summary:
            'Enriches every inbound lead with company context before a rep opens the record.',
        description:
            'Takes a new lead, gathers company size, funding, tech stack, and recent news, then writes a short brief on the record so the first call starts from research instead of a blank page.',
        trigger: 'New lead created',
        apps: ['HubSpot', 'Web search', 'Slack'],
        steps: [
            'Look up the company and the person who signed up',
            'Gather funding, headcount, and recent announcements',
            'Score the lead against your ideal customer profile',
            'Write the brief onto the CRM record',
        ],
        isFeatured: false,
    },
    {
        slug: 'follow-up-writer',
        name: 'Follow-up Writer',
        category: 'Sales',
        icon: Send,
        summary:
            'Drafts the post-call follow-up email while the conversation is still fresh.',
        description:
            'Reads the call notes and the deal history, then drafts a follow-up that answers what was actually asked, restates the next step, and waits in the rep’s drafts for a quick edit and send.',
        trigger: 'Call notes saved to a deal',
        apps: ['Gmail', 'HubSpot'],
        steps: [
            'Read the call notes and previous emails on the deal',
            'Draft a follow-up in the rep’s own voice',
            'Attach the collateral that was promised on the call',
            'Save it as a draft for review',
        ],
        isFeatured: false,
    },
    {
        slug: 'ticket-summarizer',
        name: 'Ticket Summarizer',
        category: 'Support',
        icon: LifeBuoy,
        summary:
            'Condenses long ticket threads into a handover an engineer can act on.',
        description:
            'Rewrites a sprawling support thread as the problem, what has been tried, the customer’s environment, and the exact reproduction steps, so escalations stop losing half their context.',
        trigger: 'Ticket escalated to engineering',
        apps: ['Zendesk', 'Linear', 'Slack'],
        steps: [
            'Read the full ticket thread and attachments',
            'Extract environment details and reproduction steps',
            'Write the handover summary',
            'Open an engineering issue and link it back',
        ],
        isFeatured: false,
    },
    {
        slug: 'release-notes-writer',
        name: 'Release Notes Writer',
        category: 'Engineering',
        icon: Megaphone,
        summary:
            'Turns merged pull requests into release notes your customers can read.',
        description:
            'Collects everything shipped since the last release, groups it into features, fixes, and internal work, and rewrites commit messages as sentences that mean something outside the codebase.',
        trigger: 'Release tag published',
        apps: ['GitHub', 'Linear', 'Slack'],
        steps: [
            'Diff the release against the previous tag',
            'Group changes by feature, fix, and internal work',
            'Rewrite each entry for a customer audience',
            'Post the notes to the release channel',
        ],
        isFeatured: true,
    },
    {
        slug: 'pull-request-reviewer',
        name: 'Pull Request Reviewer',
        category: 'Engineering',
        icon: GitPullRequestArrow,
        summary:
            'Leaves a first-pass review on every pull request within a minute of it opening.',
        description:
            'Reviews the diff against your team’s conventions, flags missing tests and risky changes, and leaves inline comments so the human reviewer starts from the interesting questions.',
        trigger: 'Pull request opened',
        apps: ['GitHub'],
        steps: [
            'Read the diff and the surrounding files it touches',
            'Check it against your recorded project rules',
            'Flag missing tests and behaviour changes',
            'Leave inline comments on the pull request',
        ],
        isFeatured: false,
    },
    {
        slug: 'invoice-matcher',
        name: 'Invoice Matcher',
        category: 'Finance',
        icon: ReceiptText,
        summary:
            'Matches incoming invoices to purchase orders and flags what does not line up.',
        description:
            'Reads invoices out of the finance inbox, pulls the amounts and line items, matches them against open purchase orders, and escalates only the ones where the numbers disagree.',
        trigger: 'Invoice received by email',
        apps: ['Gmail', 'QuickBooks', 'Stripe'],
        steps: [
            'Extract line items, totals, and dates from the invoice',
            'Find the matching purchase order',
            'Reconcile the amounts and flag discrepancies',
            'File the invoice or escalate to the finance owner',
        ],
        isFeatured: false,
    },
    {
        slug: 'expense-auditor',
        name: 'Expense Auditor',
        category: 'Finance',
        icon: FileSearch,
        summary:
            'Checks submitted expenses against policy and chases the missing receipts.',
        description:
            'Reviews every expense report against your written policy, approves the ones that clearly comply, and messages the submitter about the ones missing a receipt or an explanation.',
        trigger: 'Expense report submitted',
        apps: ['Expensify', 'Slack', 'Knowledge'],
        steps: [
            'Read the report and your expense policy page',
            'Check each line against the policy limits',
            'Approve compliant reports automatically',
            'Ask the submitter for anything missing',
        ],
        isFeatured: false,
    },
    {
        slug: 'content-repurposer',
        name: 'Content Repurposer',
        category: 'Marketing',
        icon: Sparkles,
        summary:
            'Reshapes one published post into drafts for every channel you publish on.',
        description:
            'Takes a long-form post and writes the shorter versions — the social thread, the newsletter blurb, the changelog entry — each in the voice that channel expects, and queues them for approval.',
        trigger: 'Blog post published',
        apps: ['Knowledge', 'LinkedIn', 'Buffer'],
        steps: [
            'Read the published post and pull out its key claims',
            'Draft a version per channel in the right voice and length',
            'Suggest an image or pull quote for each',
            'Queue the drafts for approval',
        ],
        isFeatured: false,
    },
    {
        slug: 'competitor-watch',
        name: 'Competitor Watch',
        category: 'Marketing',
        icon: Radar,
        summary:
            'Tracks competitor releases and pricing changes and reports only what changed.',
        description:
            'Watches the pages and feeds you care about, compares them to last week’s snapshot, and writes a digest of the differences that actually matter instead of another firehose of alerts.',
        trigger: 'Weekly schedule',
        apps: ['Web search', 'Knowledge', 'Slack'],
        steps: [
            'Fetch the tracked pages, changelogs, and feeds',
            'Diff them against the previous snapshot',
            'Summarise what changed and why it matters',
            'Post the digest to your marketing channel',
        ],
        isFeatured: false,
    },
    {
        slug: 'standup-digest',
        name: 'Standup Digest',
        category: 'Operations',
        icon: ClipboardList,
        summary:
            'Writes the daily standup from the work that actually moved yesterday.',
        description:
            'Reads issue activity, merged pull requests, and channel updates, then posts a per-person digest of what shipped, what is blocked, and what has not moved in a week.',
        trigger: 'Every weekday morning',
        apps: ['Linear', 'GitHub', 'Slack'],
        steps: [
            'Collect yesterday’s issue and pull request activity',
            'Group it by person and by project',
            'Call out blockers and stalled work',
            'Post the digest before standup',
        ],
        isFeatured: false,
    },
    {
        slug: 'knowledge-curator',
        name: 'Knowledge Curator',
        category: 'Operations',
        icon: BookMarked,
        summary:
            'Keeps your knowledge base honest by flagging pages the code has outgrown.',
        description:
            'Cross-checks knowledge pages against the systems they describe, flags the ones that have drifted, and drafts the corrections for an owner to accept or reject.',
        trigger: 'Weekly schedule',
        apps: ['Knowledge', 'GitHub', 'Slack'],
        steps: [
            'Scan pages that have not been touched recently',
            'Compare each claim against the current source of truth',
            'Draft corrections for the pages that have drifted',
            'Notify each page owner with the suggested edit',
        ],
        isFeatured: false,
    },
    {
        slug: 'onboarding-buddy',
        name: 'Onboarding Buddy',
        category: 'People',
        icon: Users,
        summary:
            'Walks a new hire through their first two weeks, one nudge at a time.',
        description:
            'Runs the onboarding checklist for each new hire: books the intro calls, shares the right documents on the day they are needed, and answers the questions everybody asks in week one.',
        trigger: 'New team member joins',
        apps: ['Slack', 'Google Calendar', 'Knowledge'],
        steps: [
            'Create the checklist for the hire’s role',
            'Book intro calls with the people they will work with',
            'Share each document on the day it is needed',
            'Answer their questions from your knowledge base',
        ],
        isFeatured: false,
    },
];
