<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationGroup;
use App\Models\Event;
use App\Models\Group;
use App\Models\Response;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResponseController extends Controller
{
    /**
     * Show the public response submission form for a given event and group.
     */
    public function showPublicForm(Request $request, int $event_id, int $group_id)
    {
        // Find the event by ID
        $event = Event::findOrFail($event_id);

        // Find the group by ID
        $group = Group::findOrFail($group_id);

        // Get active conversations for this event
        $conversations = Conversation::where('event_id', $event->id)
            ->where('status', 'active')
            ->get(['id', 'title', 'description']);

        // Check which conversations already have an existing response for this group
        $existingResponses = ConversationGroup::where('group_id', $group->id)
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->whereHas('responses')
            ->pluck('conversation_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();

        return Inertia::render('public/respond', [
            'event_id' => $event->id,
            'group_id' => $group->id,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'start_date' => $event->start_date?->format('Y-m-d'),
                'location' => $event->location,
            ],
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
            ],
            'conversations' => $conversations,
            'existing_responses' => $existingResponses,
        ]);
    }

    /**
     * Store a new anonymous consensus paragraph response.
     */
    public function storePublicResponse(Request $request, int $event_id, int $group_id)
    {
        // Find the event by ID
        $event = Event::findOrFail($event_id);

        // Find the group by ID
        $group = Group::findOrFail($group_id);

        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'consensus_text' => 'required|string|max:5000',
            'confirm_overwrite' => 'nullable|boolean',
        ]);

        // Find or create ConversationGroup for this conversation and group
        $conversationGroup = ConversationGroup::firstOrCreate([
            'conversation_id' => $validated['conversation_id'],
            'group_id' => $group->id,
        ]);

        // Check if a response already exists for this ConversationGroup
        $existing = Response::where('conversation_group_id', $conversationGroup->id)->latest()->first();

        if ($existing && empty($validated['confirm_overwrite'])) {
            return back()->withErrors([
                'existing_response' => 'A response has already been submitted for this group in this session.',
            ]);
        }

        if ($existing) {
            $existing->update([
                'consensus_text' => $validated['consensus_text'],
                'submitted_at' => now(),
            ]);
        } else {
            Response::create([
                'conversation_group_id' => $conversationGroup->id,
                'consensus_text' => $validated['consensus_text'],
                'submitted_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Your response has been submitted successfully.');
    }

    /**
     * Show the response submission form (authenticated).
     */
    public function create(Request $request)
    {
        $user = $request->user();

        // Get active conversations (sessions)
        $conversations = Conversation::where('status', 'active')->get(['id', 'title', 'description']);

        // Get all groups if user is admin/synthesis/drafter so they can submit on behalf of a group
        $groups = Group::orderBy('name')->get(['id', 'name']);

        return Inertia::render('public/respond', [
            'groups' => $groups,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Store a new consensus paragraph response (authenticated).
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'group_id' => 'required|exists:groups,id',
            'consensus_text' => 'required|string|max:5000',
            'confirm_overwrite' => 'nullable|boolean',
        ]);

        $conversationGroup = ConversationGroup::firstOrCreate([
            'conversation_id' => $validated['conversation_id'],
            'group_id' => $validated['group_id'],
        ]);

        // Check if a response already exists for this session and group
        $existing = Response::where('conversation_group_id', $conversationGroup->id)->latest()->first();

        if ($existing && empty($validated['confirm_overwrite'])) {
            return back()->withErrors([
                'existing_response' => 'A response has already been submitted for this group in this session.',
            ]);
        }

        if ($existing) {
            $existing->update([
                'consensus_text' => $validated['consensus_text'],
                'submitted_at' => now(),
            ]);
        } else {
            Response::create([
                'conversation_group_id' => $conversationGroup->id,
                'consensus_text' => $validated['consensus_text'],
                'submitted_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Your response has been submitted successfully.');
    }
}
