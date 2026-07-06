<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\FinalDocument;
use App\Models\Response;
use App\Models\SynthesisReport;
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
        $reports = SynthesisReport::where('conversation_id', $selectedConversationId)
            ->where('status', 'submitted')
            ->with(['synthesis'])
            ->get();

        // Fetch documents
        $documents = FinalDocument::with('conversations')->latest()->get();

        // Stats
        $stats = [
            'total_reports' => SynthesisReport::where('conversation_id', $selectedConversationId)->count(),
            'submitted_reports' => SynthesisReport::where('conversation_id', $selectedConversationId)->where('status', 'submitted')->count(),
            'draft_reports' => SynthesisReport::where('conversation_id', $selectedConversationId)->where('status', 'draft')->count(),
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
    public function show(Request $request, SynthesisReport $report)
    {
        $report->load(['synthesis', 'conversation']);

        // Fetch ALL responses for this conversation (draughters have access to all)
        $responses = Response::where('conversation_id', $report->conversation_id)
            ->with(['group', 'facilitator'])
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
        $reports = SynthesisReport::where('status', 'submitted')
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

        // Create a final document (booklet)
        $document = FinalDocument::create([
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'],
            'content_json' => $parsed,
            'emerging_themes' => $parsed['emerging_themes'] ?? $parsed['emerging_themes_text'] ?? '',
            'created_by' => $request->user()->id,
        ]);

        // Attach conversations
        $document->conversations()->attach($conversationIds);

        return back()->with('success', 'Global synthesis booklet created using '.$result['provider'].' provider.');
    }

    /**
     * Show the export page for a final document (booklet).
     */
    public function export(Request $request, FinalDocument $document = null)
    {
        if ($document) {
            $document->load(['conversations', 'event']);

            $reports = SynthesisReport::where('status', 'submitted')
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

        $reports = SynthesisReport::where('status', 'submitted')
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
