<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

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
                // Get media files from storage/app/public/media/{conversation-title-lowercase}/ folder
                $mediaFiles = [];
                $folder = strtolower(str_replace(' ', '', $conversation->title));
                $storagePath = 'media/' . $folder;

                // Check both old location (public/{folder}) and new location (storage/media/{folder})
                $oldPath = public_path($folder);
                $newPath = storage_path('app/public/' . $storagePath);

                $files = [];
                if (is_dir($newPath)) {
                    $files = scandir($newPath);
                } elseif (is_dir($oldPath)) {
                    // Fallback to old location for backward compatibility
                    $files = scandir($oldPath);
                }

                if ($files) {
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && is_file(
                            is_dir($newPath) ? "{$newPath}/{$file}" : "{$oldPath}/{$file}"
                        )) {
                            $mediaFiles[] = [
                                'name' => $file,
                                'path' => '/storage/' . $storagePath . '/' . $file,
                                'size' => filesize(
                                    is_dir($newPath) ? "{$newPath}/{$file}" : "{$oldPath}/{$file}"
                                ),
                            ];
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
        $storagePath = 'media/' . $folderName;
        $basePath = storage_path('app/public/' . $storagePath);

        // Create the conversation folder if it doesn't exist
        if (!is_dir($basePath)) {
            Storage::disk('public')->makeDirectory($storagePath, 0755, true);
        }

        $uploadedFiles = [];

        foreach ($request->file('files') as $file) {
            $originalFilename = $file->getClientOriginalName();
            $timestamp = time() . '_' . Str::random(8);
            $extension = $file->getClientOriginalExtension();
            $slugName = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME));
            $fileName = $slugName . '_' . $timestamp . '.' . $extension;

            // Store file using Storage facade
            Storage::disk('public')->put($storagePath . '/' . $fileName, file_get_contents($file->getRealPath()));

            $uploadedFiles[] = [
                'name' => $fileName,
                'original_name' => $originalFilename,
                'size' => $file->getSize(),
                'path' => '/storage/' . $storagePath . '/' . $fileName,
            ];
        }

        return response()->json([
            'success' => true,
            'uploaded_files' => $uploadedFiles,
        ]);
    }
}
