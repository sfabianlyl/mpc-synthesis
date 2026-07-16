<?php

namespace App\Services;

use App\Models\UploadedDocumentSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\PdfParser;

class PdfUploadService
{
    private int $maxFileSize;
    private string $cleanLevel;

    public function __construct()
    {
        $this->maxFileSize = (int) config('services.pdf.max_file_size', 52428800); // 50MB
        $this->cleanLevel = config('services.pdf.clean_level', 'standard');
    }

    /**
     * Upload a PDF file and extract text content.
     *
     * @param  UploadedFile  $file  The uploaded PDF file
     * @param  int  $uploadedBy  The user ID who uploaded
     * @param  array  $metadata  Additional metadata (title, document_type, author, publication_date)
     */
    public function uploadAndExtract(UploadedFile $file, int $uploadedBy, array $metadata = []): UploadedDocumentSource
    {
        // Generate unique file path
        $originalFilename = $file->getClientOriginalName();
        $fileName = Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'_'.time().'.pdf';
        $filePath = 'document-sources/pdfs/'.$fileName;

        // Store the file
        $file->storeAs('document-sources/pdfs', $fileName, 'public');

        // Create the source record
        $source = UploadedDocumentSource::create([
            'title' => $metadata['title'] ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            'original_filename' => $originalFilename,
            'file_path' => $filePath,
            'document_type' => $metadata['document_type'] ?? null,
            'author' => $metadata['author'] ?? null,
            'publication_date' => $metadata['publication_date'] ?? null,
            'processing_status' => 'pending',
            'uploaded_by' => $uploadedBy,
        ]);

        // Process the document asynchronously
        dispatch(function () use ($source) {
            try {
                $source->markAsProcessing();

                // Extract text from PDF
                $rawText = $this->extractText($source->file_path);

                // Clean and convert to markdown
                $cleanedMarkdown = $this->cleanAndConvertToMarkdown($rawText);

                // Store cleaned markdown
                $source->update([
                    'cleaned_markdown' => $cleanedMarkdown,
                    'processing_status' => 'completed',
                ]);

                Log::info('PDF processed successfully.', [
                    'source_id' => $source->id,
                    'title' => $source->title,
                    'text_length' => strlen($cleanedMarkdown),
                ]);
            } catch (\Exception $e) {
                Log::error('PDF processing failed.', [
                    'source_id' => $source->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $source->markAsFailed($e->getMessage());
            }
        })->catch(function (\Throwable $e) use ($source) {
            Log::error('PDF processing job failed.', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            $source->markAsFailed('Job failed: '.$e->getMessage());
        });

        return $source;
    }

    /**
     * Extract raw text from a PDF file.
     */
    protected function extractText(string $filePath): string
    {
        $fullPath = Storage::disk('public')->path($filePath);

        if (! file_exists($fullPath)) {
            throw new \RuntimeException('PDF file not found: '.$filePath);
        }

        Log::info('Extracting text from PDF.', ['file' => $filePath]);

        $parser = new PdfParser();
        $pdf = $parser->parseFile($fullPath);
        $text = $pdf->getText([
            'page' => null, // All pages
        ]);

        $pdf->destroy();

        if (empty($text)) {
            throw new \RuntimeException('No text could be extracted from the PDF. The file may contain scanned images.');
        }

        return $text;
    }

    /**
     * Clean extracted text and convert to markdown format.
     */
    protected function cleanAndConvertToMarkdown(string $rawText): string
    {
        // Remove publication noise based on clean level
        $text = match ($this->cleanLevel) {
            'minimal' => $this->removeMinimalNoise($rawText),
            'standard' => $this->removePublicationNoise($rawText),
            'aggressive' => $this->removeAggressiveNoise($rawText),
            default => $this->removePublicationNoise($rawText),
        };

        // Convert to markdown format
        return $this->convertToMarkdown($text);
    }

    /**
     * Remove minimal noise (just normalize whitespace).
     */
    protected function removeMinimalNoise(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Normalize multiple blank lines to two newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Remove publication noise (publisher info, ISSN, ISBN, page numbers, etc.).
     */
    protected function removePublicationNoise(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove publisher information blocks (Vatican Press, etc.)
        $text = preg_replace('/(?i)reproduced\s+with\s+(the\s+)?permission\s+of.*?\n?/i', '', $text);
        $text = preg_replace('/(?i)copyright\s+.*?\n?/i', '', $text);
        $text = preg_replace('/(?i)c\.\s*\d{4}.*?\n?/i', '', $text);

        // Remove ISSN numbers
        $text = preg_replace('/(?i)ISSN\s*[:\-]?\s*\d{4}\s*\-?\s*\d{3}[\dX]/i', '', $text);

        // Remove ISBN numbers
        $text = preg_replace('/(?i)ISBN\s*[:\-]?\s*\d{10,13}/i', '', $text);
        $text = preg_replace('/(?i)ISBN\s*[:\-]?\s*\d{3}\s*\d{1,5}\s*\d{1,7}\s*\d{1}/i', '', $text);

        // Remove "Printed in..." lines
        $text = preg_replace('/(?i)printed\s+in\s+.*?\n?/i', '', $text);

        // Remove page number patterns at line boundaries
        $text = preg_replace('/(?i)^Page\s+\d+\s*$/m', '', $text);
        $text = preg_replace('/(?i)^p\.\s*\d+\s*$/m', '', $text);
        $text = preg_replace('/(?i)^\d+\s*$/m', '', $text);

        // Remove headers/footers (repeated short lines at start/end of pages)
        // Detect repeated patterns that appear multiple times
        $lines = explode("\n", $text);
        $lineCounts = array_count_values(array_map('trim', $lines));
        $repeatedLines = array_keys(array_filter($lineCounts, fn ($count) => $count > 3));

        if (! empty($repeatedLines)) {
            foreach ($repeatedLines as $repeatedLine) {
                if (strlen($repeatedLine) < 100 && preg_match('/^(vatican|press|edition|copy)/i', $repeatedLine)) {
                    $text = str_replace($repeatedLine, '', $text);
                }
            }
        }

        // Remove publication metadata blocks at the beginning
        $text = preg_replace('/^[\s\S]*?(?=^ [A-Z])/', '', $text, 1, $count);

        // Normalize whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Aggressive noise removal (for difficult PDFs).
     */
    protected function removeAggressiveNoise(string $text): string
    {
        $text = $this->removePublicationNoise($text);

        // Remove any remaining metadata-like blocks at the start
        $text = preg_replace('/^[\s\S]*?(?=[A-Z][a-z]+\s+[A-Z])/m', '', $text, 1);

        // Remove table of contents patterns
        $text = preg_replace('/(?i)table\s+of\s+contents.*?(?=chapter|\d+\.\d+|part\s+)/is', '', $text);

        // Remove any remaining standalone numbers (page numbers)
        $text = preg_replace('/(?<!\w)\d{1,3}(?!\w)/', '', $text);

        return trim($text);
    }

    /**
     * Convert plain text to markdown format.
     */
    protected function convertToMarkdown(string $text): string
    {
        // Detect and convert section headings (lines that look like headings)
        $lines = explode("\n", $text);
        $markdownLines = [];
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines (we'll add them back for paragraph breaks)
            if (empty($trimmed)) {
                if ($inList) {
                    $markdownLines[] = '';
                    $inList = false;
                }
                continue;
            }

            // Detect heading-like patterns (ALL CAPS, short lines at start, numbered sections)
            if ($this->looksLikeHeading($trimmed)) {
                if ($inList) {
                    $markdownLines[] = '';
                    $inList = false;
                }
                $markdownLines[] = '# '.$trimmed;
                $markdownLines[] = '';
                continue;
            }

            // Detect sub-headings (short capitalized lines)
            if ($this->looksLikeSubHeading($trimmed)) {
                if ($inList) {
                    $markdownLines[] = '';
                    $inList = false;
                }
                $markdownLines[] = '## '.$trimmed;
                $markdownLines[] = '';
                continue;
            }

            // Detect list items (lines starting with numbers, bullets, or dashes)
            if (preg_match('/^(\d+[\.\)]|\-|\*|\•)\s+(.*)/', $trimmed, $matches)) {
                if (! $inList) {
                    $inList = true;
                }
                $markdownLines[] = '- '.$matches[2];
                continue;
            }

            // Regular paragraph text
            if ($inList && ! preg_match('/^[\-\*\•]/', $trimmed)) {
                $markdownLines[] = '';
                $inList = false;
            }

            $markdownLines[] = $trimmed;
        }

        return trim(implode("\n", $markdownLines));
    }

    /**
     * Check if a line looks like a main heading.
     */
    protected function looksLikeHeading(string $line): bool
    {
        // ALL CAPS lines that are not too long (likely headings)
        if ($line === strtoupper($line) && strlen($line) < 100 && strlen($line) > 3) {
            return true;
        }

        // Numbered section headings like "1.", "2.1", "Part I"
        if (preg_match('/^(\d+[\.\)]\s*|[A-Z]+[\.\)]\s*)([A-Z][a-z]/', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a line looks like a sub-heading.
     */
    protected function looksLikeSubHeading(string $line): bool
    {
        // Short capitalized phrase (title case) that's not a common sentence start
        $words = explode(' ', $line);
        if (count($words) < 15 && count($words) > 1) {
            $capitalizedCount = 0;
            foreach ($words as $word) {
                if (preg_match('/^[A-Z]/', $word)) {
                    $capitalizedCount++;
                }
            }
            // If more than 60% of words are capitalized, likely a heading
            return ($capitalizedCount / count($words)) > 0.6;
        }

        return false;
    }

    /**
     * Re-process an existing document (for retry after failure).
     */
    public function reprocess(UploadedDocumentSource $source): void
    {
        try {
            $source->markAsProcessing();

            $rawText = $this->extractText($source->file_path);
            $cleanedMarkdown = $this->cleanAndConvertToMarkdown($rawText);

            $source->update([
                'cleaned_markdown' => $cleanedMarkdown,
                'processing_status' => 'completed',
                'processing_error' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('PDF reprocessing failed.', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);

            $source->markAsFailed($e->getMessage());
        }
    }
}
