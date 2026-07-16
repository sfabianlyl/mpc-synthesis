<?php

namespace App\Services;

use App\Models\DocumentSourceChunk;
use App\Models\UploadedDocumentSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentChunkingService
{
    private EmbeddingService $embeddingService;
    private PdfUploadService $pdfUploadService;

    public function __construct(EmbeddingService $embeddingService, PdfUploadService $pdfUploadService)
    {
        $this->embeddingService = $embeddingService;
        $this->pdfUploadService = $pdfUploadService;
    }

    /**
     * Chunk a processed document and store chunks with embeddings.
     *
     * @return int The number of chunks created
     */
    public function chunkDocument(UploadedDocumentSource $source, array $options = []): int
    {
        if (empty($source->cleaned_markdown)) {
            // If no markdown yet, extract it first
            $this->pdfUploadService->reprocess($source);
            $source->refresh();

            if (empty($source->cleaned_markdown)) {
                throw new \RuntimeException('No content available to chunk. PDF extraction may have failed.');
            }
        }

        $options = array_merge([
            'chunk_size' => 1000,       // tokens (approximate characters)
            'overlap' => 200,           // characters overlap between chunks
            'min_chunk_size' => 200,    // minimum characters per chunk
            'respect_headings' => true, // try to split at heading boundaries
        ], $options);

        // Delete existing chunks for this source (in case of re-chunking)
        DocumentSourceChunk::where('source_id', $source->id)->delete();

        // Split markdown into chunks
        $chunks = $this->splitIntoChunks($source->cleaned_markdown, $options);

        // Generate embeddings and store chunks
        $chunkCount = 0;
        $batchSize = 10;
        $batchTexts = [];
        $batchChunks = [];

        foreach ($chunks as $index => $chunkData) {
            $chunkData['chunk_index'] = $index;
            $batchTexts[] = $chunkData['content'];
            $batchChunks[] = $chunkData;

            // Process in batches for efficiency
            if (count($batchTexts) >= $batchSize || $index === count($chunks) - 1) {
                $embeddings = $this->embeddingService->batchGenerate($batchTexts);

                foreach ($batchChunks as $i => $chunkData) {
                    $embedding = $embeddings[$i] ?? null;

                    if (empty($embedding)) {
                        Log::warning('Failed to generate embedding for chunk.', [
                            'source_id' => $source->id,
                            'chunk_index' => $chunkData['chunk_index'],
                        ]);
                    }

                    DocumentSourceChunk::create([
                        'source_id' => $source->id,
                        'chunk_index' => $chunkData['chunk_index'],
                        'chunk_content' => $chunkData['content'],
                        'embedding' => $embedding,
                        'title' => $source->title,
                        'section_heading' => $chunkData['section_heading'] ?? null,
                        'page_start' => $chunkData['page_start'] ?? null,
                        'page_end' => $chunkData['page_end'] ?? null,
                        'word_count' => $chunkData['word_count'],
                    ]);

                    $chunkCount++;
                }

                $batchTexts = [];
                $batchChunks = [];
            }
        }

        // Update source with chunk count
        $source->markAsCompleted($chunkCount);

        Log::info('Document chunked successfully.', [
            'source_id' => $source->id,
            'chunk_count' => $chunkCount,
        ]);

        return $chunkCount;
    }

    /**
     * Split markdown text into overlapping chunks with metadata preservation.
     */
    protected function splitIntoChunks(string $markdown, array $options): array
    {
        $chunks = [];
        $respectHeadings = $options['respect_headings'];
        $chunkSize = $options['chunk_size'] * 5; // Rough token-to-character conversion (1 token ~ 5 chars)
        $overlap = $options['overlap'];
        $minChunkSize = $options['min_chunk_size'];

        if ($respectHeadings) {
            return $this->splitByHeadings($markdown, $chunkSize, $overlap, $minChunkSize);
        }

        // Simple character-based chunking
        $content = trim($markdown);
        $length = strlen($content);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);
            $chunkContent = substr($content, $start, $end - $start);

            // Try to break at a sentence boundary
            if ($end < $length) {
                $lastPeriod = strrpos($chunkContent, '. ');
                $lastNewline = strrpos($chunkContent, "\n\n");
                $breakPoint = max($lastPeriod !== false ? $lastPeriod + 1 : 0, $lastNewline !== false ? $lastNewline + 2 : 0);

                if ($breakPoint > $chunkSize * 0.5) {
                    $end = $start + $breakPoint;
                    $chunkContent = substr($content, $start, $end - $start);
                }
            }

            // Extract section heading from the chunk
            $sectionHeading = $this->extractSectionHeading($chunkContent);

            $chunks[] = [
                'content' => trim($chunkContent),
                'section_heading' => $sectionHeading,
                'page_start' => null,
                'page_end' => null,
                'word_count' => str_word_count($chunkContent),
            ];

            $start = $end - $overlap;
        }

        // Filter out chunks that are too small
        return array_filter($chunks, fn ($chunk) => strlen($chunk['content']) >= $minChunkSize);
    }

    /**
     * Split markdown by heading boundaries for better context preservation.
     */
    protected function splitByHeadings(string $markdown, int $chunkSize, int $overlap, int $minChunkSize): array
    {
        $chunks = [];
        $lines = explode("\n", $markdown);

        // Group lines by section (sections start with # headings)
        $sections = [];
        $currentSection = '';
        $currentContent = [];

        foreach ($lines as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/', trim($line), $matches)) {
                if ($currentSection || empty($currentContent)) {
                    if (! empty($currentContent)) {
                        $sections[$currentSection] = implode("\n", $currentContent);
                    }
                    $currentSection = $matches[1];
                    $currentContent = [$line];
                } else {
                    $currentContent[] = $line;
                }
            } else {
                $currentContent[] = $line;
            }
        }

        if ($currentSection || ! empty($currentContent)) {
            $sections[$currentSection] = implode("\n", $currentContent);
        }

        // Split each section into chunks if needed
        foreach ($sections as $sectionHeading => $sectionContent) {
            $sectionLines = explode("\n", trim($sectionContent));

            if (count($sectionLines) <= 1) {
                continue; // Skip empty or single-line sections
            }

            // If section is small enough, keep it as one chunk
            $contentLength = strlen(implode("\n", $sectionLines));
            if ($contentLength <= $chunkSize) {
                $chunks[] = [
                    'content' => implode("\n", $sectionLines),
                    'section_heading' => $sectionHeading,
                    'page_start' => null,
                    'page_end' => null,
                    'word_count' => str_word_count(implode("\n", $sectionLines)),
                ];

                continue;
            }

            // Split large section into smaller chunks
            $currentChunk = [];
            $currentLength = 0;

            foreach ($sectionLines as $line) {
                if ($currentLength + strlen($line) > $chunkSize && ! empty($currentChunk)) {
                    // Save current chunk with overlap from next
                    $chunks[] = [
                        'content' => implode("\n", $currentChunk),
                        'section_heading' => $sectionHeading,
                        'page_start' => null,
                        'page_end' => null,
                        'word_count' => str_word_count(implode("\n", $currentChunk)),
                    ];

                    // Keep last few lines for overlap
                    $overlapLines = array_slice($currentChunk, -max(2, (int) ($overlap / 50)));
                    $currentChunk = $overlapLines;
                    $currentLength = strlen(implode("\n", $currentChunk));
                }

                $currentChunk[] = $line;
                $currentLength += strlen($line);
            }

            if (! empty($currentChunk)) {
                $chunks[] = [
                    'content' => implode("\n", $currentChunk),
                    'section_heading' => $sectionHeading,
                    'page_start' => null,
                    'page_end' => null,
                    'word_count' => str_word_count(implode("\n", $currentChunk)),
                ];
            }
        }

        // Filter out chunks that are too small
        return array_filter($chunks, fn ($chunk) => strlen($chunk['content']) >= $minChunkSize);
    }

    /**
     * Extract the section heading from a chunk of text.
     */
    protected function extractSectionHeading(string $content): ?string
    {
        // Look for markdown heading in first few lines
        $lines = array_slice(explode("\n", $content), 0, 5);

        foreach ($lines as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/', trim($line), $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Get all chunks for a source document.
     */
    public function getChunks(UploadedDocumentSource $source): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentSourceChunk::where('source_id', $source->id)
            ->orderBy('chunk_index')
            ->get();
    }

    /**
     * Delete all chunks for a source (called when source is deleted).
     */
    public function deleteChunks(int $sourceId): void
    {
        DocumentSourceChunk::where('source_id', $sourceId)->delete();
    }
}
