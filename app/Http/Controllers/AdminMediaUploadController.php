<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class AdminMediaUploadController extends Controller
{
    /**
     * Display the media upload page for all conversations.
     */
    public function index(): Response
    {
        $conversations = Conversation::with(['event'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'status', 'description', 'event_id', 'created_at'])
            ->map(function ($conversation) {
                // Get media files from public/{conversation-title-lowercase}/ folder
                $mediaFiles = [];
                if ($conversation->event_id) {
                    $folder = strtolower(str_replace(' ', '', $conversation->title));
                    $basePath = public_path($folder);
                    if (is_dir($basePath)) {
                        foreach (scandir($basePath) as $file) {
                            if ($file !== '.' && $file !== '..' && is_file("{$basePath}/{$file}")) {
                                $mediaFiles[] = [
                                    'name' => $file,
                                    'path' => "/{$folder}/{$file}",
                                    'size' => filesize("{$basePath}/{$file}"),
                                ];
                            }
                        }
                    }
                }

                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'status' => $conversation->status,
                    'description' => $conversation->description,
                    'event_id' => $conversation->event_id,
                    'event_title' => $conversation->event?->title,
                    'media_files' => $mediaFiles,
                ];
            });

        return Inertia::render('admin/MediaUploadPage', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Store uploaded media files for a specific conversation.
     */
    public function store(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:52428800|mimes:pdf,jpg,png,gif,webp,mpeg,wav,mp4,qt',
        ]);

        $conversation = Conversation::findOrFail($conversationId);

        // Determine the folder name: lowercase, spaces removed
        $folderName = strtolower(str_replace(' ', '', $conversation->title));
        $basePath = public_path($folderName);

        // Create the conversation folder if it doesn't exist
        if (! is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $uploadedFiles = [];

        foreach ($request->file('files') as $file) {
            $originalFilename = $file->getClientOriginalName();
            $timestamp = time() . '_' . Str::random(8);
            $extension = $file->getClientOriginalExtension();
            $slugName = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
            $fileName = $slugName.'_'.$timestamp.'.'.$extension;

            $file->storeAs($basePath, $fileName, 'public');

            $uploadedFiles[] = [
                'name' => $fileName,
                'original_name' => $originalFilename,
                'size' => $file->getSize(),
                'path' => "/{$folderName}/{$fileName}",
            ];
        }

        return response()->json([
            'success' => true,
            'uploaded_files' => $uploadedFiles,
        ]);
    }
}
