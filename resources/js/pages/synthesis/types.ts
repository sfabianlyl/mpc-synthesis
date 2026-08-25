import type { SynthesisSubmission, MasterSynthesisReport } from '@/types/models';

// ─── Type Definitions ─────────────────────────────────────────────

export interface SynthesisMember {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_online?: boolean;
    editing_category?: string | null;
}

/**
 * Grouped synthesis contributions for a single category from all members.
 */
export interface CategoryContributions {
    content: string;    // Unified content (editable)
    submissions: Array<{
        id: number;
        user: { id: number; name: string };
        content: string | null;
        created_at: string;
        updated_at: string;
    }>;
}

export interface SynthesisSummaryData {
    id: number;
    convergence_summary: string | null;
    consideration_summary: string | null;
    proposals_summary: string | null;
    other_summary: string | null;
    submitted_drafts_count?: number | null;
    status: string;
    is_successful: boolean;
    provider: string | null;
    generated_at: string | null;
}

export interface MasterSynthesisEditor {
    convergence: CategoryContributions;
    matters_for_consideration: CategoryContributions;
    proposals: CategoryContributions;
    others: CategoryContributions;
}

export interface CollaboratePageProps {
    conversation: any;
    submissions: SynthesisSubmission[];
    masterSynthesis: MasterSynthesisReport | null;
    synthesisSummary: SynthesisSummaryData | null;
    groups: any[];
    responses: any[];
    consensusSubmissions: Record<number, any[]>;
    currentUser: { id: number; name: string; email: string; role: string };
    isSynthesizer?: boolean;
    onlineUsers?: SynthesisMember[];
    retrievedChunks?: any[];
    success?: string;
    errors?: Record<string, string>;
}
