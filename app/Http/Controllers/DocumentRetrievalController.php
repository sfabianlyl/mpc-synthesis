<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\DocumentChunkingService;
use App\Services\DocumentRetrievalService;
use App\Services\EmbeddingService;
use App\Services\PdfUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;

class DocumentRetrievalController extends BaseController
{
    /**
     * Retrieve relevant chunks for booklet generation.
     */
    public function retrieve(Request $request, DocumentRetrievalService $retrievalService): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:10'],
            'limit' => ['integer', 'min:1', 'max:50'],
            'min_similarity' => ['numeric', 'min:0', 'max:1'],
            'source_ids' => ['array'],
            'source_ids.*' => ['integer'],
            'document_type' => ['string'],
        ]);

        $query = $validated['q'];
        $options = array_filter([
            'limit' => $validated['limit'] ?? 10,
            'min_similarity' => $validated['min_similarity'] ?? 0.0,
            'source_ids' => $validated['source_ids'] ?? null,
            'document_type' => $validated['document_type'] ?? null,
        ], fn ($value) => $value !== null);

        $chunks = $retrievalService->retrieveRelevantChunks($query, $options);

        return response()->json([
            'chunks' => $chunks,
            'total_results' => count($chunks),
            'query' => $query,
        ]);
    }

    /**
     * Get all chunks from a specific source document.
     */
    public function getChunks(int $sourceId, DocumentRetrievalService $retrievalService): JsonResponse
    {
        $chunks = $retrievalService->retrieveBySource($sourceId);

        return response()->json([
            'source_id' => $sourceId,
            'chunks' => $chunks,
            'total_chunks' => $chunks->count(),
        ]);
    }

    /**
     * Search chunks by keyword (fallback when embeddings are not available).
     */
    public function search(Request $request, DocumentRetrievalService $retrievalService): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3'],
            'limit' => ['integer', 'min:1', 'max:50'],
            'source_ids' => ['array'],
            'source_ids.*' => ['integer'],
        ]);

        $query = $validated['q'];
        $options = array_filter([
            'limit' => $validated['limit'] ?? 10,
            'source_ids' => $validated['source_ids'] ?? null,
        ], fn ($value) => $value !== null);

        $results = $retrievalService->searchByKeyword($query, $options);

        return response()->json([
            'chunks' => $results,
            'total_results' => count($results),
            'query' => $query,
            'method' => 'keyword',
        ]);
    }

    /**
     * Search for relevant document chunks using semantic similarity.
     * 
     * Accepts a query string and optional conversation context to find
     * relevant document chunks with their source metadata for citation.
     */
    public function searchChunks(Request $request, DocumentRetrievalService $retrievalService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'limit' => ['integer', 'min:1', 'max:50'],
            'min_similarity' => ['numeric', 'min:0', 'max:1'],
            'source_ids' => ['array'],
            'source_ids.*' => ['integer'],
        ]);

        $query = $validated['query'];
        
        // Filter by conversation's uploaded documents if conversation_id is provided
        $sourceIds = $validated['source_ids'] ?? null;
        if ($validated['conversation_id'] && empty($sourceIds)) {
            $conversationSourceIds = \App\Models\UploadedDocumentSource::where('conversation_id', $validated['conversation_id'])
                ->pluck('id')
                ->toArray();
            
            if (!empty($conversationSourceIds)) {
                $sourceIds = $conversationSourceIds;
            }
        }

        $options = array_filter([
            'limit' => $validated['limit'] ?? 10,
            'min_similarity' => $validated['min_similarity'] ?? 0.0,
            'source_ids' => $sourceIds,
        ], fn ($value) => $value !== null);

        $chunks = $retrievalService->retrieveRelevantChunks($query, $options);

        // Enrich chunks with citation-ready metadata
        $enrichedChunks = collect($chunks)->map(function ($chunk) {
            return [
                'chunk_id' => $chunk['chunk_id'] ?? $chunk['id'] ?? null,
                'content' => $chunk['content'] ?? '',
                'similarity_score' => $chunk['similarity_score'] ?? $chunk['score'] ?? null,
                'document_name' => $chunk['document_name'] ?? $chunk['source_name'] ?? null,
                'document_type' => $chunk['document_type'] ?? null,
                'page_number' => $chunk['page_number'] ?? null,
                'source_id' => $chunk['source_id'] ?? null,
                'citation' => $this->buildCitation($chunk),
            ];
        })->toArray();

        return response()->json([
            'chunks' => $enrichedChunks,
            'total_results' => count($enrichedChunks),
            'query' => $query,
            'method' => 'semantic',
        ]);
    }

    /**
     * Build a citation string for a document chunk.
     */
    private function buildCitation(array $chunk): string
    {
        $documentName = $chunk['document_name'] ?? $chunk['source_name'] ?? 'Unknown Document';
        $pageNumber = $chunk['page_number'] ?? null;
        $chunkId = $chunk['chunk_id'] ?? $chunk['id'] ?? null;

        $citation = "[$documentName]";
        if ($pageNumber) {
            $citation .= " p.{$pageNumber}";
        }
        if ($chunkId) {
            $citation .= " (chunk #{$chunkId})";
        }

        return $citation;
    }

    /**
     * Get all available document sources.
     */
    public function getSources(DocumentRetrievalService $retrievalService): JsonResponse
    {
        $sources = $retrievalService->getAvailableSources();

        return response()->json([
            'sources' => $sources,
        ]);
    }

    /**
     * Get document statistics.
     */
    public function getStatistics(DocumentRetrievalService $retrievalService): JsonResponse
    {
        $stats = $retrievalService->getStatistics();

        return response()->json([
            'statistics' => $stats,
        ]);
    }

    /**
     * Upload a PDF document and create source records with embeddings.
     */
    public function uploadDocument(Request $request, PdfUploadService $pdfUploadService): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:50000', 'mimes:pdf'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'document_name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $validated['file'];
        $originalName = $file->getClientOriginalName();
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        
        $path = $file->storeAs('documents/uploads', $fileName, 'public');

        // Create uploaded document source record
        $source = \App\Models\UploadedDocumentSource::create([
            'conversation_id' => $validated['conversation_id'] ?? null,
            'user_id' => $request->user()->id,
            'file_path' => $path,
            'original_name' => $originalName,
            'document_name' => $validated['document_name'] ?? $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        // Process the PDF: extract text, create chunks, and generate embeddings
        $result = $pdfUploadService->processUploadedPdf($source);

        return response()->json([
            'success' => true,
            'source' => [
                'id' => $source->id,
                'document_name' => $source->document_name,
                'original_name' => $source->original_name,
                'file_size' => $source->file_size,
                'chunk_count' => $result['chunk_count'] ?? 0,
                'embedding_status' => $result['embeddings_created'] ? 'completed' : 'pending',
            ],
        ]);
    }

    /**
     * Delete an uploaded document and its associated chunks/embeddings.
     */
    public function deleteDocument(int $sourceId, DocumentChunkingService $chunkingService): JsonResponse
    {
        $source = \App\Models\UploadedDocumentSource::findOrFail($sourceId);
        
        // Delete file from storage
        if (Storage::disk('public')->exists($source->file_path)) {
            Storage::disk('public')->delete($source->file_path);
        }

        // Delete chunks and embeddings
        $chunkingService->deleteSourceChunks($sourceId);

        $source->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }

    /**
     * Get all uploaded documents for a conversation.
     */
    public function getConversationDocuments(int $conversationId): JsonResponse
    {
        $documents = \App\Models\UploadedDocumentSource::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->get([
                'id',
                'conversation_id',
                'user_id',
                'original_name',
                'document_name',
                'file_size',
                'mime_type',
                'chunk_count',
                'embedding_status',
                'created_at',
            ]);

        return response()->json([
            'documents' => $documents,
            'total' => $documents->count(),
        ]);
    }
}
