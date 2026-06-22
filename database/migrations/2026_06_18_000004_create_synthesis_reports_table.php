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
        Schema::create('synthesis_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('synthesis_id')->constrained('users')->cascadeOnDelete();
            $table->text('convergence_text')->nullable();
            $table->text('consideration_text')->nullable();
            $table->text('proposals_text')->nullable();
            $table->boolean('llm_generated')->default(false);
            $table->string('status')->default('draft'); // draft, reviewed, submitted
            $table->timestamps();

            // Ensure unique synthesis report per conversation and synthesis member
            $table->unique(['conversation_id', 'synthesis_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synthesis_reports');
    }
};
