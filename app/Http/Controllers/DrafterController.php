<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\DocumentSourceChunk;
use App\Models\Event;
use App\Models\FinalDocument;
use App\Models\Response;
use App\Models\MasterSynthesis;
use App\Services\DocumentRetrievalService;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DrafterController extends Controller
{
    /**
     * Show the drafter dashboard.
     */
    public function index(Request $request)
    {
        // Fetch all events with their conversations
        $events = Event::with('conversations')
            ->orderBy('start_date', 'desc')
            ->get();

        // Fetch all conversations (sessions)
        $conversations = Conversation::orderBy('created_at', 'desc')->get(['id', 'title', 'status', 'event_id']);

        if ($conversations->isEmpty()) {
            return Inertia::render('drafter/dashboard', [
                'events' => $events,
                'conversations' => [],
                'selectedConversationId' => null,
                'reports' => [],
                'documents' => [],
                'stats' => [
                    'total_reports' => 0,
                    'submitted_reports' => 0,
                    'draft_reports' => 0,
                    'documents_created' => 0,
                ],
            ]);
        }

        // Selected conversation
        $selectedConversationId = $request->input('conversation_id');
        if (!$selectedConversationId) {
            $activeConv = $conversations->where('status', 'active')->first();
            $selectedConversationId = $activeConv ? $activeConv->id : $conversations->first()->id;
        }
        $selectedConversationId = (int) $selectedConversationId;

        // Fetch reports for the selected conversation
        $reports = MasterSynthesis::where('conversation_id', $selectedConversationId)
            ->where('status', 'submitted')
            ->with(['synthesis'])
            ->get();

        // Fetch documents
        $documents = FinalDocument::with('conversations')->latest()->get();

        // Stats
        $stats = [
            'total_reports' => MasterSynthesis::where('conversation_id', $selectedConversationId)->count(),
            'submitted_reports' => MasterSynthesis::where('conversation_id', $selectedConversationId)->where('status', 'submitted')->count(),
            'draft_reports' => MasterSynthesis::where('conversation_id', $selectedConversationId)->where('status', 'draft')->count(),
            'documents_created' => FinalDocument::count(),
        ];

        return Inertia::render('drafter/dashboard', [
            'events' => $events,
            'conversations' => $conversations,
            'selectedConversationId' => $selectedConversationId,
            'reports' => $reports,
            'documents' => $documents,
            'stats' => $stats,
        ]);
    }

    /**
     * Show a detailed view of a specific report with drill-down to original responses.
     */
    public function show(Request $request, MasterSynthesis $report)
    {
        $report->load(['synthesis', 'conversation']);

        // Fetch ALL responses for this conversation (draughters have access to all)
        $responses = Response::where('conversation_id', $report->conversation_id)
            ->with('group')
            ->get();

        return Inertia::render('drafter/report-detail', [
            'report' => $report,
            'responses' => $responses,
        ]);
    }

    /**
     * Trigger global synthesis across selected sessions/conversations.
     */
    public function globalSynthesis(Request $request, LlmService $llm)
    {
        $validated = $request->validate([
            'conversation_ids' => 'required|array',
            'conversation_ids.*' => 'exists:conversations,id',
            'title' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,id',
        ]);

        $conversationIds = $validated['conversation_ids'];

        // Get all submitted synthesis reports for these conversations
        $reports = MasterSynthesis::where('status', 'submitted')
            ->whereIn('conversation_id', $conversationIds)
            ->with(['conversation', 'synthesis'])
            ->get();

        if ($reports->isEmpty()) {
            return back()->withErrors(['synthesis' => 'No submitted reports available for the selected sessions.']);
        }

        // Compile all reports into a single text block
        $compiledText = $reports->map(function ($report) {
            return "Session: {$report->conversation->title}\n"
                ."Synthesis Member: {$report->synthesis->name}\n\n"
                ."CONVERGENCE:\n{$report->convergence_text}\n\n"
                ."MATTERS FOR CONSIDERATION:\n{$report->consideration_text}\n\n"
                ."PROPOSALS:\n{$report->proposals_text}";
        })->implode("\n\n===========================\n\n");

        $result = $llm->query(
            $llm->buildGlobalSynthesisPrompt(),
            $compiledText
        );

        if (! $result['success']) {
            return back()->withErrors(['synthesis' => 'Global synthesis failed: '.$result['error']]);
        }

        $parsed = json_decode($result['content'], true);

        if (! $parsed) {
            Log::warning('Global synthesis returned non-JSON response.', [
                'content' => $result['content'],
            ]);
            $parsed = [
                'emerging_themes' => $result['content'],
                'key_convergences' => '',
                'critical_considerations' => '',
                'priority_proposals' => '',
            ];
        }

        // Create a final document (booklet) with AI-generated flag
        $document = FinalDocument::create([
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'],
            'content_json' => $parsed,
            'emerging_themes' => $parsed['emerging_themes'] ?? $parsed['emerging_themes_text'] ?? '',
            'is_ai_generated' => true,
            'drafted_by' => $request->user()->id,
        ]);

        // Attach conversations
        $document->conversations()->attach($conversationIds);

        return back()->with('success', 'Global synthesis booklet created using '.$result['provider'].' provider.');
    }

    /**
     * Generate a booklet with source citations from master syntheses and retrieved document chunks.
     * 
     * This method:
     * 1. Fetches all master syntheses for the given event's conversations
     * 2. Uses DocumentRetrievalService to fetch relevant document chunks as citations
     * 3. Assembles a prompt with synthesized content + source citations
     * 4. Calls LLM to generate a final booklet with proper attribution
     * 5. Creates a FinalDocument with is_ai_generated = true and stores source references
     */
    public function generateBookletWithSources(Request $request, Event $event, LlmService $llm, DocumentRetrievalService $retrievalService)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'conversation_ids' => 'nullable|array',
            'conversation_ids.*' => 'exists:conversations,id',
            'max_chunks_per_category' => 'nullable|integer|min:1|max:20',
            'query_templates' => 'nullable|array',
        ]);

        // Get conversations for this event (or use provided IDs)
        $conversationIds = $validated['conversation_ids'] ?? $event->conversations()->pluck('conversations.id')->toArray();

        if (empty($conversationIds)) {
            return back()->withErrors(['booklet' => 'No conversations found for this event.']);
        }

        // Fetch all master syntheses for these conversations
        $masterSyntheses = MasterSynthesis::whereIn('conversation_id', $conversationIds)
            ->where('is_master_synthesis', true)
            ->with(['synthesis', 'conversation'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        if ($masterSyntheses->isEmpty()) {
            return back()->withErrors(['booklet' => 'No master syntheses found for the selected conversations.']);
        }

        // Organize master syntheses by category
        $categories = [
            'convergence' => [],
            'matters_for_consideration' => [],
            'proposals' => [],
            'others' => [],
        ];

        foreach ($masterSyntheses as $synthesis) {
            $categories['convergence'][] = [
                'content' => $synthesis->ai_convergence_text ?? $synthesis->convergence_text ?? '',
                'source_id' => $synthesis->id,
                'conversation_title' => $synthesis->conversation->title,
                'author' => $synthesis->synthesis->name,
                'submitted_at' => $synthesis->submitted_at?->toIso8601String(),
            ];
            $categories['matters_for_consideration'][] = [
                'content' => $synthesis->ai_consideration_text ?? $synthesis->consideration_text ?? '',
                'source_id' => $synthesis->id,
                'conversation_title' => $synthesis->conversation->title,
                'author' => $synthesis->synthesis->name,
                'submitted_at' => $synthesis->submitted_at?->toIso8601String(),
            ];
            $categories['proposals'][] = [
                'content' => $synthesis->ai_proposals_text ?? $synthesis->proposals_text ?? '',
                'source_id' => $synthesis->id,
                'conversation_title' => $synthesis->conversation->title,
                'author' => $synthesis->synthesis->name,
                'submitted_at' => $synthesis->submitted_at?->toIso8601String(),
            ];
            $categories['others'][] = [
                'content' => $synthesis->ai_other_text ?? $synthesis->other_text ?? '',
                'source_id' => $synthesis->id,
                'conversation_title' => $synthesis->conversation->title,
                'author' => $synthesis->synthesis->name,
                'submitted_at' => $synthesis->submitted_at?->toIso8601String(),
            ];
        }

        // Fetch relevant document chunks for citations
        $maxChunks = $validated['max_chunks_per_category'] ?? 5;
        $sourceChunks = [];
        $retrievedDocumentChunkIds = [];

        foreach ($categories as $categoryKey => $syntheses) {
            if (empty($syntheses)) {
                continue;
            }

            // Combine content for query
            $combinedContent = implode("\n\n", array_column($syntheses, 'content'));
            
            // Use custom query template if provided
            $query = $validated['query_templates'][$categoryKey] ?? "Summary of key points from: {$combinedContent}";

            // Retrieve relevant document chunks
            $chunks = $retrievalService->retrieveRelevantChunks($query, [
                'limit' => $maxChunks,
                'source_ids' => null,
            ]);

            if (!empty($chunks)) {
                $sourceChunks[$categoryKey] = $chunks;
                
                // Track retrieved document chunk IDs for citation references
                foreach ($chunks as $chunk) {
                    if (isset($chunk['chunk_id'])) {
                        $retrievedDocumentChunkIds[] = $chunk['chunk_id'];
                    }
                }
            }
        }

        // Build the booklet generation prompt with source citations
        $promptParts = [];
        $promptParts[] = "You are a professional document drafter. Create a comprehensive, well-structured booklet based on the following synthesis data.";
        $promptParts[] = "\n\n## INSTRUCTIONS";
        $promptParts[] = "1. Synthesize all input into a coherent final document";
        $promptParts[] = "2. Include proper source citations for all content";
        $promptParts[] = "3. Use the format [Source: Report ID #xxx] for synthesis reports";
        $promptParts[] = "4. Use the format [Document: {document_name} chunk #xxx] for document chunks";
        $promptParts[] = "5. Organize by category: Convergence, Matters for Consideration, Proposals, Others";

        // Add master synthesis content
        foreach ($categories as $categoryKey => $syntheses) {
            if (empty($syntheses)) {
                continue;
            }
            $promptParts[] = "\n\n## {$categoryKey}";
            foreach ($syntheses as $i => $synthesis) {
                $promptParts[] = "\n### Source: {$synthesis['author']} (Report #{$synthesis['source_id']}) - {$synthesis['conversation_title']}";
                $promptParts[] = $synthesis['content'];
            }
        }

        // Add document chunk citations
        if (!empty($sourceChunks)) {
            $promptParts[] = "\n\n## SUPPORTING DOCUMENT CITATIONS";
            foreach ($sourceChunks as $categoryKey => $chunks) {
                $promptParts[] = "\n### {$categoryKey}";
                foreach ($chunks as $chunk) {
                    $docName = $chunk['document_name'] ?? 'Unknown Document';
                    $promptParts[] = "[Document: {$docName} chunk #{$chunk['chunk_id']}]: {$chunk['content']}";
                }
            }
        }

        // Generate the booklet using LLM
        $result = $llm->query(
            "Create a professional, well-structured final document based on the synthesis data. Include proper citations and attribution for all sources.",
            implode("\n", $promptParts)
        );

        if (! $result['success']) {
            return back()->withErrors(['booklet' => 'Booklet generation failed: '.$result['error']]);
        }

        // Parse the LLM response
        $parsedContent = json_decode($result['content'], true);
        $finalContent = $parsedContent ?? $result['content'];

        // Collect source report IDs
        $sourceReportIds = $masterSyntheses->pluck('id')->toArray();

        // Create the final document with AI-generated flag and source references
        $document = FinalDocument::create([
            'event_id' => $event->id,
            'title' => $validated['title'],
            'content' => is_array($finalContent) ? json_encode($finalContent) : $finalContent,
            'is_ai_generated' => true,
            'drafted_by' => $request->user()->id,
            'source_report_ids' => $sourceReportIds,
            'retrieved_chunk_references' => array_unique($retrievedDocumentChunkIds),
            'drafted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'is_ai_generated' => $document->is_ai_generated,
                'drafted_by' => $document->drafted_by,
                'source_report_ids' => $document->source_report_ids,
                'retrieved_chunk_references' => $document->retrieved_chunk_references,
                'drafted_at' => $document->drafted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get all synthesis reports for an event (for booklet generation).
     */
    public function getEventReports(Request $request, Event $event)
    {
        $conversations = $event->conversations()->pluck('conversations.id')->toArray();

        $reports = MasterSynthesis::whereIn('conversation_id', $conversations)
            ->with(['synthesis', 'conversation'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'conversation_id' => $report->conversation_id,
                    'conversation_title' => $report->conversation->title,
                    'user_id' => $report->synthesis_id,
                    'user_name' => $report->synthesis->name,
                    'is_master_synthesis' => $report->is_master_synthesis,
                    'is_llm_assisted' => $report->is_llm_assisted,
                    'categories' => [
                        'convergence' => [
                            'ai_content' => $report->ai_convergence_text ?? '',
                            'manual_content' => $report->convergence_text ?? '',
                        ],
                        'matters_for_consideration' => [
                            'ai_content' => $report->ai_consideration_text ?? '',
                            'manual_content' => $report->consideration_text ?? '',
                        ],
                        'proposals' => [
                            'ai_content' => $report->ai_proposals_text ?? '',
                            'manual_content' => $report->proposals_text ?? '',
                        ],
                        'others' => [
                            'ai_content' => $report->ai_other_text ?? '',
                            'manual_content' => $report->other_text ?? '',
                        ],
                    ],
                    'submitted_at' => $report->submitted_at?->toIso8601String(),
                ];
            });

        return response()->json(['reports' => $reports]);
    }

    /**
     * Show the export page for a final document (booklet).
     */
    public function export(Request $request, FinalDocument $document = null)
    {
        if ($document) {
            $document->load(['conversations', 'event']);

            $reports = MasterSynthesis::where('status', 'submitted')
                ->whereIn('conversation_id', $document->conversations->pluck('id'))
                ->with(['synthesis', 'conversation'])
                ->get();

            return Inertia::render('drafter/export', [
                'document' => $document,
                'reports' => $reports,
            ]);
        }

        // For global synthesis: pass available conversations and events
        $conversations = Conversation::with('event')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'status', 'event_id', 'created_at']);

        $events = Event::orderBy('start_date', 'desc')->get(['id', 'title', 'start_date']);

        return Inertia::render('drafter/export', [
            'document' => null,
            'reports' => [],
            'conversations' => $conversations,
            'events' => $events,
        ]);
    }

    /**
     * Generate and download a PDF booklet.
     */
    public function downloadPdf(Request $request, FinalDocument $document)
    {
        $document->load(['conversations', 'event']);

        $reports = MasterSynthesis::where('status', 'submitted')
            ->whereIn('conversation_id', $document->conversations->pluck('id'))
            ->with(['synthesis', 'conversation'])
            ->get();

        $html = view('pdf.synthesis-booklet', [
            'document' => $document,
            'reports' => $reports,
        ])->render();

        $filename = 'synthesis-booklet-'.now()->format('Y-m-d').'.pdf';

        // Store path reference
        $document->update(['exported_file_path' => $filename]);

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'.html"');
    }
}
