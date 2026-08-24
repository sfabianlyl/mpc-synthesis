export interface User {
    id: number;
    name: string;
    email: string;
    role: 'synthesis' | 'drafter' | 'admin' | 'pastoral' | 'discernment';
    created_at: string;
    diocese_id?: number | null;
}

export interface Conversation {
    id: number;
    title: string;
    description?: string;
    status: 'active' | 'completed' | 'closed' | 'draft';
    event_id: number | null;
    event_title: string | null;
    media_files: Array<{ name: string; path: string; size: number }>;
    created_at: string;
    updated_at: string;
}

export interface Group {
    id: number;
    name: string;
    responses_count?: number;
    responses?: Response[];
}

export interface ConversationGroup {
    id: number;
    conversation_id: number;
    group_id: number;
    group?: Group;
    responses?: Response[];
}

export interface Response {
    id: number;
    conversation_group_id: number;
    group_id?: number;
    consensus_text: string;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    group?: Group;
    conversationGroup?: ConversationGroup;
}

export interface ConsensusNote {
    id: number;
    conversation_id: number;
    user_id: number;
    response_id: number;
    text: string | null;
    created_at?: string;
    updated_at?: string;
}

/**
 * SynthesisReport represents an individual synthesis submission from a synthesis member.
 */
export interface SynthesisReport {
    id: number;
    conversation_id: number;
    user_id: number;
    synthesis_id: number;
    convergence_text?: string;
    consideration_text?: string;
    proposals_text?: string;
    other_text?: string;
    
    is_master_synthesis?: boolean;
    status: 'draft' | 'reviewed' | 'submitted';
    submitted_at?: string | null;
    created_at: string;
    updated_at: string;
    
    // Relationships
    synthesis?: User;
    conversation?: Conversation;
}

/**
 * SynthesisSubmission represents a submitted synthesis for the collaborative review page.
 */
export interface SynthesisSubmission {
    id: number;
    user: { id: number; name: string; email: string };
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    other_text: string | null;
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

/**
 * MasterSynthesisReport represents the "synthesis of synthesis" crafted during
 * the Collaborative Review session. It aggregates content from all individual syntheses.
 */
export interface MasterSynthesisReport {
    id: number | null;
    conversation_id: number;
    user_id: number | null;
    is_master_synthesis: boolean;
    group_ids: number[];
    
    convergence_text?: string | null;
    consideration_text?: string | null;
    proposals_text?: string | null;
    other_text?: string | null;
    
    status: 'draft' | 'reviewed' | 'submitted';
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Payload for creating/updating a master synthesis via the API.
 */
export interface MasterSynthesisPayload {
    conversation_id: number;
    categories: {
        convergence: { content: string };
        matters_for_consideration: { content: string };
        proposals: { content: string };
        others: { content: string };
    };
}

export interface SynthesisCustomQuery {
    id: number;
    synthesis_report_id: number;
    prompt: string;
    response: string[];
    provider: string | null;
    created_at: string;
}

/**
 * FinalDocument represents a booklet or final document generated from syntheses.
 * Can be AI-generated (is_ai_generated = true) or manually drafted.
 */
export interface FinalDocument {
    id: number;
    event_id: number | null;
    title: string;
    content: string;
    content_json?: {
        emerging_themes?: string;
        key_convergences?: string;
        critical_considerations?: string;
        priority_proposals?: string;
    };
    emerging_themes?: string;
    
    // AI generation flags
    is_ai_generated: boolean;
    drafted_by: number | null;
    drafted_at: string | null;
    
    // Source tracking for AI-generated documents
    source_report_ids: number[];
    retrieved_chunk_references: number[];
    
    // Export tracking
    exported_file_path?: string;
    created_by?: number;
    created_at: string;
    updated_at: string;
    
    // Relationships
    conversations?: Conversation[];
    event?: Event;
    creator?: User;
    draftedBy?: User;
}

export interface ConsensusSubmission {
    id: number;
    group_id?: number;
    group_name: string | null;
    submitted_at: string | null;
    consensus_text: string;
    is_submitted: boolean;
}

export interface UploadedDocumentSource {
    id: number;
    conversation_id: number | null;
    user_id: number | null;
    original_name: string;
    document_name: string;
    file_path: string;
    file_size: number | null;
    mime_type: string | null;
    chunk_count: number;
    embedding_status: 'pending' | 'completed' | 'failed';
    processing_status: 'pending' | 'processing' | 'completed' | 'failed';
    processing_error: string | null;
    uploaded_by: number | null;
    created_at: string;
    updated_at: string;
    uploader?: { id: number; name: string; email: string };
}

export interface DocumentSourceChunk {
    id: number;
    source_id: number;
    chunk_index: number;
    chunk_content: string;
    embedding: number[] | null;
    title: string | null;
    section_heading: string | null;
    page_start: number | null;
    page_end: number | null;
    word_count: number;
    created_at: string;
    updated_at: string;
    source?: UploadedDocumentSource;
}

/**
 * Enriched chunk response from searchChunks endpoint with citation metadata.
 */
export interface EnrichedDocumentChunk {
    chunk_id: number | null;
    content: string;
    similarity_score: number | null;
    document_name: string | null;
    document_type: string | null;
    page_number: number | null;
    source_id: number | null;
    citation: string;
}

export interface DocumentRetrievalResponse {
    chunks: Array<{
        id: number;
        chunk_content: string;
        title: string | null;
        section_heading: string | null;
        page_start: number | null;
        page_end: number | null;
        similarity_score: number;
        word_count: number;
    }>;
    total_results: number;
    query_time_ms?: number;
}

export interface Event {
    id: number;
    title: string;
    start_date: string;
    end_date: string | null;
    location?: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

/**
 * DecisionSummary represents a diocese-level decision summary.
 */
export interface DecisionSummary {
    id: number;
    diocese_id: number | null;
    national_summary: string | null;
    diocesan_summary: string | null;
    provider: string | null;
    is_finalized: boolean;
    created_at: string;
    updated_at: string;
    diocese?: {
        id: number;
        name: string;
        code: string;
    };
}

/**
 * DecisionDraft represents an individual's decision draft submission per conversation.
 */
export interface DecisionDraft {
    id: number;
    conversation_id: number;
    user_id: number;
    diocese_id: number | null;
    national_text: string | null;
    diocesan_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
    diocese?: {
        id: number;
        name: string;
        code: string;
    };
}

/**
 * DecisionPart represents the shared decision part for a conversation.
 */
export interface DecisionPart {
    id: number;
    conversation_id: number;
    submitted_by_user_id: number | null;
    national_text: string | null;
    diocesan_text: string | null;
    consolidated_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    generated_by: string | null;
    generated_at: string | null;
    created_at: string;
    updated_at: string;
    conversation?: Conversation;
    submitted_by?: {
        id: number;
        name: string;
        email: string;
    } | string;
}

/**
 * MasterDecision represents the event-level master decision.
 */
export interface MasterDecision {
    id: number;
    event_id: number;
    submitted_by_user_id: number | null;
    master_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    generated_by: string | null;
    generated_at: string | null;
    created_at: string;
    updated_at: string;
    submitted_by?: {
        id: number;
        name: string;
        email: string;
    } | string;
}


/**
 * Diocese represents a diocese entity.
 */
export interface Diocese {
    id: number;
    name: string;
    code: string;
    created_at: string;
    updated_at: string;
}

export type Stats = {
    total_reports: number;
    submitted_reports: number;
    draft_reports: number;
    documents_created: number;
};

/**
 * NationalDecisionDraft represents a national-level decision draft by a pastoral member for an event.
 */
export interface NationalDecisionDraft {
    id: number;
    event_id: number;
    user_id: number;
    diocese_id: number | null;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    user?: { id: number; name: string; email: string };
    diocese?: { id: number; name: string; code: string };
}

/**
 * DioceseDecisionDraft represents a diocese-level decision draft by a pastoral member for an event.
 */
export interface DioceseDecisionDraft {
    id: number;
    event_id: number;
    user_id: number;
    diocese_id: number | null;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    created_at: string;
    updated_at: string;
    user?: { id: number; name: string; email: string };
    diocese?: { id: number; name: string; code: string };
}

/**
 * MasterDecisionSummary represents the consolidated master decision summary for an event.
 */
export interface MasterDecisionSummary {
    id: number;
    event_id: number;
    master_text: string | null;
    is_editable: boolean;
    generated_by: string | null;
    generated_at: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * MediaFile represents a downloadable media file from a conversation's media folder.
 */
export interface MediaFile {
    name: string;
    path: string;
    size: number;
}
