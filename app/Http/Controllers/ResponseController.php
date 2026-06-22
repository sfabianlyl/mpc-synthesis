<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\Response;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResponseController extends Controller
{
    /**
     * Show the facilitator submission form.
     */
    public function create(Request $request)
    {
        $user = $request->user();
        
        // Find assigned group for facilitator
        $assignedGroup = Group::where('facilitator_id', $user->id)->first();
        
        // Get active conversations (sessions)
        $conversations = Conversation::where('status', 'active')->get(['id', 'title', 'description']);
        
        // Get all groups if user is admin/synthesis/drafter so they can submit on behalf of a group
        $groups = [];
        if (!$user->isFacilitator() || !$assignedGroup) {
            $groups = Group::orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('facilitator/submit', [
            'groups' => $groups,
            'currentGroup' => $assignedGroup,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Store a new consensus paragraph response.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'group_id' => 'required|exists:groups,id',
            'consensus_text' => 'required|string|max:5000',
        ]);

        // Check if group facilitator is trying to submit for a group they do not own
        $assignedGroup = Group::where('facilitator_id', $user->id)->first();
        if ($user->isFacilitator() && $assignedGroup && $assignedGroup->id !== (int) $validated['group_id']) {
            return back()->withErrors(['group_id' => 'You are only authorized to submit responses for your assigned group.']);
        }

        // Check if a response already exists for this session and group
        $existing = Response::where('conversation_id', $validated['conversation_id'])
            ->where('group_id', $validated['group_id'])
            ->first();

        if ($existing) {
            return back()->withErrors(['consensus_text' => 'A response has already been submitted for this group in this session.']);
        }

        Response::create([
            'conversation_id' => $validated['conversation_id'],
            'group_id' => $validated['group_id'],
            'facilitator_id' => $user->id,
            'consensus_text' => $validated['consensus_text'],
            'submitted_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Your response has been submitted successfully.');
    }
}
