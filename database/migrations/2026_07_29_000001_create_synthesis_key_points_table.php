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
        Schema::create('synthesis_key_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('master_synthesis_id');
            $table->unsignedInteger('user_id'); // Who triggered the extraction
            $table->string('source_category')->nullable(); // Which category or 'all' for global
            $table->text('key_points_text'); // Raw bullet points text from LLM
            $table->json('key_points')->nullable(); // Parsed array of key points
            $table->unsignedSmallInteger('requested_count'); // Number of key points requested
            $table->string('provider')->nullable(); // 'local', 'gemini', or 'mock'
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'master_synthesis_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synthesis_key_points');
    }
};
