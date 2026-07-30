import { CheckCircle2, FileText, Sparkles, MessageSquare } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

// ─── Category Configuration ───────────────────────────────────────

export const CATEGORIES = [
    { 
        key: 'convergence', 
        label: 'Convergence', 
        icon: CheckCircle2 as LucideIcon,
        description: 'Points of agreement across all groups',
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/20',
        borderColor: 'border-emerald-200 dark:border-emerald-800',
    },
    { 
        key: 'consideration', 
        label: 'Matters for Consideration', 
        icon: FileText as LucideIcon,
        description: 'Critical issues requiring attention',
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-50 dark:bg-amber-950/20',
        borderColor: 'border-amber-200 dark:border-amber-800',
    },
    { 
        key: 'proposals', 
        label: 'Proposals', 
        icon: Sparkles as LucideIcon,
        description: 'Actionable recommendations and suggestions',
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950/20',
        borderColor: 'border-blue-200 dark:border-blue-800',
    },
    { 
        key: 'others', 
        label: 'Others', 
        icon: MessageSquare as LucideIcon,
        description: 'Additional insights and observations',
        color: 'text-violet-600 dark:text-violet-400',
        bgColor: 'bg-violet-50 dark:bg-violet-950/20',
        borderColor: 'border-violet-200 dark:border-violet-800',
    },
] as const;

export type CategoryKey = typeof CATEGORIES[number]['key'];
