<?php

namespace App\Http\Controllers;

use App\Models\OpenForumQuestion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpenForumController extends Controller
{
    /**
     * Show the open forum question submission form (no auth required).
     */
    public function index()
    {
        return Inertia::render('open-forum/submit');
    }

    /**
     * Store a submitted open forum question.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:5000',
        ]);

        OpenForumQuestion::create([
            'question' => $validated['question'],
        ]);

        return back()->with('success', 'Your questions have been submitted successfully.');
    }

    /**
     * List all submitted open forum questions (no auth required).
     */
    public function submittedQuestions()
    {
        $questions = OpenForumQuestion::latest('created_at')->get(['id', 'question', 'created_at']);

        return Inertia::render('open-forum/submitted-questions', [
            'questions' => $questions,
        ]);
    }
}
