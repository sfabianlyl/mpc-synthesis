export interface User {
    id: number;
    name: string;
    email: string;
    role: 'synthesis' | 'drafter' | 'admin';
    created_at: string;
}

export interface Conversation {
    id: number;
    title: string;
    description?: string;
    status: 'active' | 'completed';
    created_at: string;
    updated_at: string;
}

export interface Group {
    id: number;
    name: string;
    synthesis_id?: number;
    responses_count?: number;
    synthesis?: User;
    responses?: Response[];
}

export interface Response {
    id: number;
    conversation_id: number;
    group_id: number;
    consensus_text: string;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    group?: Group;
}

export interface SynthesisReport {
    id: number;
    conversation_id: number;
    synthesis_id: number;
    submission_number?: number;
    convergence_text?: string;
    consideration_text?: string;
    proposals_text?: string;
    other_text?: string;
    ai_convergence_text?: string | null;
    ai_consideration_text?: string | null;
    ai_proposals_text?: string | null;
    ai_other_text?: string | null;
    is_llm_assisted?: boolean;
    llm_generated: boolean;
    status: 'draft' | 'reviewed' | 'submitted';
    submitted_at?: string | null;
    created_at: string;
    updated_at: string;
    synthesis?: User;
    conversation?: Conversation;
}

export interface SynthesisSubmission {
    id: number;
    submission_number: number;
    user: { id: number; name: string; email: string };
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    other_text: string | null;
    ai_convergence_text: string | null;
    ai_consideration_text: string | null;
    ai_proposals_text: string | null;
    ai_other_text: string | null;
    is_llm_assisted: boolean;
    status: 'draft' | 'reviewed' | 'submitted';
    submitted_at: string | null;
    custom_queries: Array<{
        id: number;
        prompt: string;
        response: string[];
        provider: string | null;
        created_at: string;
    }>;
}

export interface MasterSynthesisReport {
    id: number | null;
    conversation_id: number;
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    other_text: string | null;
    llm_generated: boolean;
    status: 'draft' | 'reviewed' | 'submitted';
    created_at: string;
    updated_at: string;
}

export interface SynthesisCustomQuery {
    id: number;
    synthesis_report_id: number;
    prompt: string;
    response: string[];
    provider: string | null;
    created_at: string;
}

export interface FinalDocument {
    id: number;
    title: string;
    content_json: {
        emerging_themes?: string;
        key_convergences?: string;
        critical_considerations?: string;
        priority_proposals?: string;
    };
    emerging_themes?: string;
    exported_file_path?: string;
    created_by?: number;
    created_at: string;
    updated_at: string;
    conversations?: Conversation[];
    creator?: User;
}

export interface ConsensusSubmission {
    id: number;
    group_id?: number;
    group_name: string | null;
    submitted_at: string | null;
    consensus_text: string;
    is_submitted: boolean;
}

export type Stats = {
    total_reports: number;
    submitted_reports: number;
    draft_reports: number;
    documents_created: number;
};
