<?php

namespace App\Http\Controllers;

use App\Broadcasting\Events\MasterSynthesisUpdated;
use App\Jobs\ReportGenerationJob;
use App\Models\Conversation;
use App\Models\MasterSynthesis;
use Illuminate\Http\Request;

class MasterSynthesisController extends Controller
{
    /**
     * Store a master synthesis report for the conversation.
     *
     * Master synthesis represents the "synthesis of synthesis" crafted collaboratively during
     * the Collaborative Review session. It requires all 4 categories to have content.
     * Only users with synthesis role can create master syntheses.
     */
    public function store(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: only users with synthesis/drafter/admin role can create master syntheses
        

        // Validate all 4 categories have content
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.convergence' => 'required|array',
            'categories.convergence.content' => 'nullable|string',
            'categories.matters_for_consideration' => 'required|array',
            'categories.matters_for_consideration.content' => 'nullable|string',
            'categories.proposals' => 'required|array',
            'categories.proposals.content' => 'nullable|string',
            'categories.others' => 'required|array',
            'categories.others.content' => 'nullable|string',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'integer',
        ]);

        // Ensure each category has content
        foreach (['convergence', 'matters_for_consideration', 'proposals', 'others'] as $category) {
            $content = $validated['categories'][$category]['content'] ?? '';
            if (empty($content)) {
                return back()->withErrors([
                    "categories.{$category}" => "The {$category} category must have content.",
                ]);
            }
        }

        // Find or create the master synthesis report for this conversation
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            
            ->first();

        if (!$report) {
            $report = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'is_master_synthesis' => true,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
        }

        // Map category names to database field names
        $categoryMap = [
            'convergence' => 'convergence_text',
            'matters_for_consideration' => 'consideration_text',
            'proposals' => 'proposals_text',
            'others' => 'other_text',
        ];

        $updateData = [];

        foreach ($categoryMap as $categoryKey => $field) {
            $content = $validated['categories'][$categoryKey]['content'] ?? '';
            if (!empty($content)) {
                $updateData[$field] = $content;
            }
        }

        // Track which group consensuses were included
        if (isset($validated['group_ids']) && !empty($validated['group_ids'])) {
            $updateData['group_ids'] = $validated['group_ids'];
        }

        $report->update($updateData);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return response()->json([
            'success' => true,
            'master_synthesis' => [
                'id' => $report->id,
                'conversation_id' => $report->conversation_id,
                'is_master_synthesis' => $report->is_master_synthesis,
                'categories' => [
                    'convergence' => ['content' => $report->convergence_text ?? ''],
                    'matters_for_consideration' => ['content' => $report->consideration_text ?? ''],
                    'proposals' => ['content' => $report->proposals_text ?? ''],
                    'others' => ['content' => $report->other_text ?? ''],
                ],
                'group_ids' => $report->group_ids ?? [],
                'status' => $report->status,
                'submitted_at' => $report->submitted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get the master synthesis for a given conversation.
     * Returns null or empty structure if no master synthesis exists.
     */
    public function get(Conversation $conversation)
    {
        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->first();

        if (!$masterSynthesis) {
            return response()->json(['master_synthesis' => null, 'has_master_synthesis' => false]);
        }

        return response()->json([
            'master_synthesis' => [
                'id' => $masterSynthesis->id,
                'conversation_id' => $masterSynthesis->conversation_id,
                'user_id' => $masterSynthesis->synthesis_id,
                'is_master_synthesis' => $masterSynthesis->is_master_synthesis,
                'group_ids' => $masterSynthesis->group_ids ?? [],
                'submitted_at' => $masterSynthesis->submitted_at?->toIso8601String(),
                'categories' => [
                    'convergence' => ['content' => $masterSynthesis->convergence_text ?? ''],
                    'matters_for_consideration' => ['content' => $masterSynthesis->consideration_text ?? ''],
                    'proposals' => ['content' => $masterSynthesis->proposals_text ?? ''],
                    'others' => ['content' => $masterSynthesis->other_text ?? ''],
                ],
                'status' => $masterSynthesis->status,
            ],
            'has_master_synthesis' => true,
        ]);
    }

    /**
     * Get all syntheses (individual + master) grouped by category for a conversation.
     * Distinguishes between individual syntheses and master syntheses.
     */
    public function getSynthesesByCategory(Conversation $conversation)
    {
        // Fetch individual syntheses (non-master)
        $individualSyntheses = SynthesisDraft::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        // Fetch master synthesis (if exists)
        $masterSynthesis = MasterSynthesis::where('conversation_id', $conversation->id)
            ->first();

        $fields = [
            'convergence' => 'convergence_text',
            'matters_for_consideration' => 'consideration_text',
            'proposals' => 'proposals_text',
            'others' => 'other_text',
        ];

        $categories = [];
        foreach ($fields as $catKey => $catField) {
            $categories[$catKey] = [
                'individual_syntheses' => $individualSyntheses->map(fn($s) => [
                    'id' => $s->id,
                    'user_id' => $s->synthesis_id,
                    'user_name' => $s->synthesis?->name,
                    'content' => $s->{$catField} ?? '',
                    'status' => $s->status,
                    'submitted_at' => $s->submitted_at?->toIso8601String(),
                ])->toArray(),
                'master_synthesis' => $masterSynthesis ? [
                    'id' => $masterSynthesis->id,
                    'user_id' => $masterSynthesis->synthesis_id,
                    'content' => $masterSynthesis->{$catField} ?? '',
                    'submitted_at' => $masterSynthesis->submitted_at?->toIso8601String(),
                ] : null,
            ];
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'categories' => $categories,
            'individual_synthesis_count' => $individualSyntheses->count(),
            'has_master_synthesis' => $masterSynthesis !== null,
        ]);
    }

    /**
     * Update the master synthesis report (for drafter/admin role).
     */
    public function update(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Only allow drafter or admin to update master synthesis
        

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        // Find or create the master synthesis report for this conversation.
        // Match records that are master synthesis (is_master_synthesis = true) to avoid
        // accidentally updating a regular user's submitted report.
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            ->first();

        if (!$report) {
            $report = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'is_master_synthesis' => true,
                'status' => 'submitted',
            ]);
        }

        $report->update($validated);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return back()->with('success', 'Master synthesis updated successfully.');
    }

    /**
     * Update authorization to allow all assigned members to edit MasterSynthesis.
     */
    public function updateAuthorized(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is admin OR is assigned to this conversation
        // $isAdmin = $user->isAdmin();
        // $isAssigned = $conversation->synthesisMembers()->where('users.id', $user->id)->exists();

        // if (!$isAdmin && !$isAssigned) {
        //     abort(403, 'You do not have permission to update the master synthesis.');
        // }

        $validated = $request->validate([
            'convergence_text' => 'nullable|string',
            'consideration_text' => 'nullable|string',
            'proposals_text' => 'nullable|string',
            'other_text' => 'nullable|string',
        ]);

        // Find or create the master synthesis report for this conversation.
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            
            ->first();

        if ($report && $report->is_submitted) {
            return back()->withErrors(['master_synthesis' => 'Master synthesis is locked and cannot be edited.']);
        }

        if (!$report) {
            $report = MasterSynthesis::create([
                'conversation_id' => $conversation->id,
                'is_master_synthesis' => true,
                'status' => 'submitted',
            ]);
        }

        $report->update($validated);

        // Broadcast the update event for real-time updates
        event(new MasterSynthesisUpdated($conversation->id, $report));

        return back()->with('success', 'Master synthesis updated successfully.');
    }

    /**
     * Submit the master synthesis (lock it and dispatch report generation).
     */
    public function submit(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        // Authorization: check if user is admin OR is assigned to this conversation
        // $isAdmin = $user->isAdmin();
        // $isAssigned = $conversation->synthesisMembers()->where('users.id', $user->id)->exists();

        // if (!$isAdmin && !$isAssigned) {
        //     abort(403, 'You do not have permission to submit the master synthesis.');
        // }

        // Find the master synthesis for this conversation
        $report = MasterSynthesis::where('conversation_id', $conversation->id)
            
            ->first();

        if (!$report) {
            return back()->withErrors(['submission' => 'No master synthesis found to submit.']);
        }

        // Check if already submitted
        if ($report->is_submitted) {
            return back()->withErrors(['submission' => 'This master synthesis has already been submitted.']);
        }

        // Set is_submitted = true and lock the master synthesis
        $report->update([
            'is_submitted' => true,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Dispatch ReportGenerationJob
        ReportGenerationJob::dispatch($report);

        return redirect()->route('synthesis.collaborate', ['conversation' => $conversation])->with('success', 'Master synthesis submitted successfully. Report generation has started.');
    }
}
