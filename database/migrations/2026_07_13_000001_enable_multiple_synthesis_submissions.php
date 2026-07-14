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
        Schema::table('synthesis_reports', function (Blueprint $table) {
            // Drop the existing unique constraint to allow multiple submissions per member per conversation
            $table->dropUnique(['conversation_id', 'synthesis_id']);

            // Add submission tracking column
            $table->integer('submission_number')->nullable()->after('synthesis_id');

            // Add "Others" category text column
            $table->text('other_text')->nullable()->after('proposals_text');

            // Add submitted_at timestamp
            $table->timestamp('submitted_at')->nullable()->after('status');

            // Add AI-assisted preview columns (for draft editing with AI)
            $table->text('ai_other_text')->nullable()->after('ai_proposals_text');
            $table->json('ai_source_group_ids')->nullable()->change();
            $table->timestamp('ai_generated_at')->nullable()->change();
            $table->boolean('is_llm_assisted')->default(false)->after('ai_generated_at');
        });

        // Add indexes for efficient querying of submissions per conversation
        Schema::table('synthesis_reports', function (Blueprint $table) {
            $table->index(['conversation_id', 'submission_number']);
            $table->index(['conversation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synthesis_reports', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'submission_number']);
            $table->dropIndex(['conversation_id', 'status']);

            $table->dropColumn([
                'submission_number',
                'other_text',
                'submitted_at',
                'ai_other_text',
                'is_llm_assisted',
            ]);

            // Restore the original unique constraint
            $table->unique(['conversation_id', 'synthesis_id']);
        });
    }
};
