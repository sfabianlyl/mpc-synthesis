<?php

namespace App\Services;

use App\Models\DocumentSourceChunk;
use App\Models\UploadedDocumentSource;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentRetrievalService
{
    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Retrieve relevant chunks for a given query using vector similarity search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantChunks(string $query, array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        $minSimilarity = $options['min_similarity'] ?? 0.0;
        $sourceIds = $options['source_ids'] ?? null;
        $documentType = $options['document_type'] ?? null;

        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generate($query);

        if (empty($queryEmbedding)) {
            Log::warning('DocumentRetrievalService: Failed to generate query embedding.');

            return [];
        }

        // Convert embedding array to PostgreSQL vector format
        $vectorStr = '['.implode(',', $queryEmbedding).']';

        // Build the query for vector similarity search
        $query = DB::table('document_source_chunks')
            ->join('uploaded_document_sources', 'document_source_chunks.source_id', '=', 'uploaded_document_sources.id')
            ->select(
                'document_source_chunks.*',
                DB::raw('1 - (document_source_chunks.embedding <=> \''.DB::getPdo()->quote($vectorStr).'\'::vector) AS similarity_score')
            )
            ->whereNotNull('document_source_chunks.embedding')
            ->orderByRaw('document_source_chunks.embedding <-> \''.DB::getPdo()->quote($vectorStr).'\'::vector');

        // Apply filters
        if ($sourceIds) {
            $query->whereIn('uploaded_document_sources.id', $sourceIds);
        }

        if ($documentType) {
            $query->where('uploaded_document_sources.document_type', $documentType);
        }

        // Execute query with limit
        $results = $query->limit($limit)->get()->toArray();

        // Filter by minimum similarity
        if ($minSimilarity > 0) {
            $results = array_filter($results, fn ($chunk) => $chunk['similarity_score'] >= $minSimilarity);
        }

        // Re-index the array
        $results = array_values($results);

        Log::info('Document retrieval completed.', [
            'query_length' => strlen($query),
            'results_count' => count($results),
            'provider' => $this->embeddingService->getProvider(),
        ]);

        return $results;
    }

    /**
     * Retrieve all chunks from a specific source document.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DocumentSourceChunk>
     */
    public function retrieveBySource(int $sourceId, array $options = []): \Illuminate\Support\Collection
    {
        $limit = $options['limit'] ?? null;

        $query = DocumentSourceChunk::where('source_id', $sourceId)
            ->orderBy('chunk_index');

        if ($limit) {
            return $query->take($limit)->get();
        }

        return $query->get();
    }

    /**
     * Retrieve chunks with full context for booklet generation.
     * Combines multiple related chunks into coherent context blocks.
     */
    public function retrieveWithFullContext(string $query, int $conversationId = null): string
    {
        $chunks = $this->retrieveRelevantChunks($query, [
            'limit' => 15,
            'min_similarity' => 0.3,
        ]);

        if (empty($chunks)) {
            return '';
        }

        // Format chunks as citation context
        $contextParts = [];
        foreach ($chunks as $chunk) {
            $title = $chunk['title'] ?? 'Unknown Document';
            $section = $chunk['section_heading'] ?? '';
            $pageStart = $chunk['page_start'] ?? null;
            $content = $chunk['chunk_content'] ?? '';

            $citation = '['.$title;
            if ($section) {
                $citation .= ', '.$section;
            }
            if ($pageStart) {
                $citation .= ', p. '.$pageStart;
            }
            $citation .= ']';

            $contextParts[] = $citation."\n\n".$content;
        }

        return implode("\n\n---\n\n", $contextParts);
    }

    /**
     * Search chunks by keyword (fallback when embeddings are not available).
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchByKeyword(string $query, array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        $sourceIds = $options['source_ids'] ?? null;

        $searchTerms = explode(' ', strtolower($query));

        $whereClause = function ($q) use ($searchTerms, $sourceIds) {
            foreach ($searchTerms as $term) {
                if (strlen($term) < 3) continue; // Skip short terms
                $q->orWhere('document_source_chunks.chunk_content', 'LIKE', '%'.$term.'%');
            }
            if ($sourceIds) {
                $q->whereIn('uploaded_document_sources.id', $sourceIds);
            }
        };

        $results = DB::table('document_source_chunks')
            ->join('uploaded_document_sources', 'document_source_chunks.source_id', '=', 'uploaded_document_sources.id')
            ->where($whereClause)
            ->select(
                'document_source_chunks.*',
                'uploaded_document_sources.title as title',
                DB::raw('0.5 AS similarity_score') // Default score for keyword search
            )
            ->limit($limit)
            ->get()
            ->toArray();

        return $results;
    }

    /**
     * Get all available document sources (for filtering).
     */
    public function getAvailableSources(): \Illuminate\Support\Collection
    {
        return UploadedDocumentSource::where('processing_status', 'completed')
            ->select('id', 'title', 'document_type', 'author', 'chunk_count')
            ->orderBy('title')
            ->get();
    }

    /**
     * Get document statistics.
     */
    public function getStatistics(): array
    {
        $stats = UploadedDocumentSource::selectRaw('
            COUNT(*) as total_documents,
            SUM(CASE WHEN processing_status = \'completed\' THEN 1 ELSE 0 END) as completed_documents,
            SUM(CASE WHEN processing_status = \'processing\' THEN 1 ELSE 0 END) as processing_documents,
            SUM(CASE WHEN processing_status = \'failed\' THEN 1 ELSE 0 END) as failed_documents,
            SUM(chunk_count) as total_chunks
        ')->first();

        return [
            'total_documents' => (int) $stats->total_documents,
            'completed_documents' => (int) $stats->completed_documents,
            'processing_documents' => (int) $stats->processing_documents,
            'failed_documents' => (int) $stats->failed_documents,
            'total_chunks' => (int) $stats->total_chunks,
        ];
    }
}
