<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $original_filename
 * @property string $file_path
 * @property string|null $document_type
 * @property string|null $author
 * @property Carbon|null $publication_date
 * @property string|null $cleaned_markdown
 * @property string $processing_status
 * @property string|null $processing_error
 * @property int|null $uploaded_by
 * @property int $chunk_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $uploader
 * @property-read DocumentSourceChunk[] $chunks
 */
class UploadedDocumentSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'original_filename',
        'file_path',
        'document_type',
        'author',
        'publication_date',
        'cleaned_markdown',
        'processing_status',
        'processing_error',
        'uploaded_by',
        'chunk_count',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'chunk_count' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the chunks for this document.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentSourceChunk::class, 'source_id');
    }

    /**
     * Check if processing is completed.
     */
    public function isCompleted(): bool
    {
        return $this->processing_status === 'completed';
    }

    /**
     * Check if processing has failed.
     */
    public function isFailed(): bool
    {
        return $this->processing_status === 'failed';
    }

    /**
     * Mark the document as being processed.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'processing_status' => 'processing',
            'processing_error' => null,
        ]);
    }

    /**
     * Mark the document as successfully processed.
     */
    public function markAsCompleted(int $chunkCount): void
    {
        $this->update([
            'processing_status' => 'completed',
            'chunk_count' => $chunkCount,
            'processing_error' => null,
        ]);
    }

    /**
     * Mark the document as failed with an error message.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'processing_status' => 'failed',
            'processing_error' => $error,
        ]);
    }

    /**
     * Get the display label for the document type.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'encyclical' => 'Encyclical',
            'pastoral_letter' => 'Pastoral Letter',
            'apostolic_exhortation' => 'Apostolic Exhortation',
            'conference_document' => 'Conference Document',
            'homily' => 'Homily',
            'general_audience' => 'General Audience',
            'other' => 'Other',
            default => ucfirst($this->document_type ?? 'unknown'),
        };
    }

    /**
     * Get the badge color for the processing status.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->processing_status) {
            'completed' => 'success',
            'processing' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }
}
