<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pgvector extension cannot be created inside a transaction block
        // So we disable transactions for this migration
        DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        // Check if pgvector is available
        $vectorAvailable = false;
        try {
            // Check if pgvector is installed
            $pdo = DB::connection()->getPdo();
            $result = $pdo->query("SELECT 1 FROM pg_extension WHERE extname = 'vector'")->fetch();
            $vectorAvailable = (bool) $result;

            if (! $vectorAvailable) {
                Log::warning('pgvector extension not installed. Vector search will use mock embeddings.');
            }
        } catch (\Exception $e) {
            Log::warning('pgvector extension check failed.', ['error' => $e->getMessage()]);
        }

        Schema::create('uploaded_document_sources', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('original_filename', 500);
            $table->string('file_path', 1000);
            $table->string('document_type', 100)->nullable();
            $table->text('author')->nullable();
            $table->date('publication_date')->nullable();
            $table->longText('cleaned_markdown')->nullable();
            $table->string('processing_status')->default('pending');
            $table->text('processing_error')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('chunk_count')->default(0);
            $table->timestamps();
        });

        Schema::create('document_source_chunks', function (Blueprint $table) use ($vectorAvailable) {
            $table->id();
            $table->foreignId('source_id')->constrained('uploaded_document_sources')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->longText('chunk_content');

            if ($vectorAvailable) {
                $table->vector('embedding', 1536)->nullable();
            }

            $table->string('title', 500)->nullable();
            $table->string('section_heading', 500)->nullable();
            $table->integer('page_start')->nullable();
            $table->integer('page_end')->nullable();
            $table->integer('word_count')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'chunk_index']);
        });

        if ($vectorAvailable) {
            // HNSW index for vector similarity search
            DB::statement('CREATE INDEX idx_document_chunks_embedding ON document_source_chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64) WHERE embedding IS NOT NULL');
        }

        // Index for source filtering
        Schema::table('document_source_chunks', function (Blueprint $table) {
            $table->index('source_id');
            $table->index('section_heading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_source_chunks');
        Schema::dropIfExists('uploaded_document_sources');
    }
};
