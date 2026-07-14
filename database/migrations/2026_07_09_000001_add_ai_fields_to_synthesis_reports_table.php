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
            // AI-generated fields (read-only on frontend, overwritten on re-trigger)
            $table->text('ai_convergence_text')->nullable()->after('proposals_text');
            $table->text('ai_consideration_text')->nullable()->after('ai_convergence_text');
            $table->text('ai_proposals_text')->nullable()->after('ai_consideration_text');

            // Track which groups were used for the AI synthesis
            $table->json('ai_source_group_ids')->nullable()->after('ai_proposals_text');

            // Timestamp of when AI synthesis was last triggered
            $table->timestamp('ai_generated_at')->nullable()->after('ai_source_group_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synthesis_reports', function (Blueprint $table) {
            $table->dropColumn([
                'ai_convergence_text',
                'ai_consideration_text',
                'ai_proposals_text',
                'ai_source_group_ids',
                'ai_generated_at',
            ]);
        });
    }
};
