<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
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
        ]);

        // Check if a response already exists for this conversation and group
        $existing = Response::where('conversation_id', $validated['conversation_id'])
            ->where('group_id', $group->id)
            ->first();

        if ($existing) {
            return back()->withErrors(['consensus_text' => 'A response has already been submitted for this group in this session.']);
        }

        Response::create([
            'conversation_id' => $validated['conversation_id'],
            'group_id' => $group->id,
            'consensus_text' => $validated['consensus_text'],
            'submitted_at' => now(),
        ]);

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
        ]);

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
            'consensus_text' => $validated['consensus_text'],
            'submitted_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Your response has been submitted successfully.');
    }
}
