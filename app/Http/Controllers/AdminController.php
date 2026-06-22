<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'role']);
        $groups = Group::with(['synthesis:id,name', 'facilitator:id,name'])
            ->withCount('responses')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/dashboard', [
            'users' => $users,
            'groups' => $groups,
        ]);
    }

    /**
     * Create a new group.
     */
    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'synthesis_id' => 'nullable|exists:users,id',
            'facilitator_id' => 'nullable|exists:users,id',
        ]);

        Group::create($validated);

        return back()->with('success', 'Group created successfully.');
    }

    /**
     * Update a user's role.
     */
    public function updateUserRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:facilitator,synthesis,drafter,admin',
        ]);

        $user->update($validated);

        return back()->with('success', 'User role updated successfully.');
    }

    /**
     * Assign a synthesis team member to a group.
     */
    public function assignSynthesis(Request $request, Group $group)
    {
        $validated = $request->validate([
            'synthesis_id' => 'required|exists:users,id',
        ]);

        $group->update($validated);

        return back()->with('success', 'Synthesis member assigned to group.');
    }

    /**
     * Assign a facilitator to a group.
     */
    public function assignFacilitator(Request $request, Group $group)
    {
        $validated = $request->validate([
            'facilitator_id' => 'required|exists:users,id',
        ]);

        $group->update($validated);

        return back()->with('success', 'Facilitator assigned to group.');
    }

    /**
     * Delete a group.
     */
    public function deleteGroup(Group $group)
    {
        $group->delete();

        return back()->with('success', 'Group deleted.');
    }
}
