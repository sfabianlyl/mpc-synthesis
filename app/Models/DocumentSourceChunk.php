<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $source_id
 * @property int $chunk_index
 * @property string $chunk_content
 * @array<float>|null $embedding
 * @property string|null $title
 * @property string|null $section_heading
 * @property int|null $page_start
 * @property int|null $page_end
 * @property int|null $word_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UploadedDocumentSource $source
 */
class DocumentSourceChunk extends Model
{
    use HasFactory;

    protected $table = 'document_source_chunks';

    protected $fillable = [
        'source_id',
        'chunk_index',
        'chunk_content',
        'embedding',
        'title',
        'section_heading',
        'page_start',
        'page_end',
        'word_count',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'page_start' => 'integer',
            'page_end' => 'integer',
            'word_count' => 'integer',
            'embedding' => 'array',
        ];
    }

    /**
     * Get the source document for this chunk.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(UploadedDocumentSource::class, 'source_id');
    }

    /**
     * Calculate cosine similarity between this chunk's embedding and a query vector.
     */
    public function getSimilarity(array $queryVector): float
    {
        if (empty($this->embedding) || empty($queryVector)) {
            return 0.0;
        }

        $dotProduct = array_sum(array_map(fn ($a, $b) => $a * $b, $this->embedding, $queryVector));
        $magA = sqrt(array_sum(array_map(fn ($v) => $v * $v, $this->embedding)));
        $magB = sqrt(array_sum(array_map(fn ($v) => $v * $v, $queryVector)));

        return $magA > 0 && $magB > 0 ? $dotProduct / ($magA * $magB) : 0.0;
    }

    /**
     * Scope to filter chunks that have embeddings.
     */
    public function scopeWithEmbedding($query)
    {
        return $query->whereNotNull('embedding');
    }
}
