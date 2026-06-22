<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('final_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('content_json')->nullable();
            $table->text('emerging_themes')->nullable();
            $table->string('exported_file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Pivot table to associate a final document (booklet) with one or more conversations
        Schema::create('conversation_final_document', function (Blueprint $table) {
            $table->foreignId('final_document_id')->constrained('final_documents')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->primary(['final_document_id', 'conversation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_final_document');
        Schema::dropIfExists('final_documents');
    }
};
