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
            // Distinguish master synthesis from individual syntheses
            $table->boolean('is_master_synthesis')->default(false)->after('id');
            
            // Array of group IDs whose consensuses were synthesized
            // (conversation_id already exists from creation migration 2026_06_18_000004)
            $table->json('group_ids')->nullable()->after('conversation_id');
        });

        Schema::table('final_documents', function (Blueprint $table) {
            // Whether the booklet was AI-generated
            $table->boolean('is_ai_generated')->default(false)->after('id');
            
            // User ID of who drafted/generated the booklet
            $table->unsignedBigInteger('drafted_by')->nullable()->after('is_ai_generated');
            
            // Array of report IDs used as sources for this booklet
            $table->json('source_report_ids')->nullable()->after('drafted_by');
        });

        // Add foreign key for drafted_by on final_documents
        Schema::table('final_documents', function (Blueprint $table) {
            $table->foreign('drafted_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('final_documents', function (Blueprint $table) {
            $table->dropForeign(['drafted_by']);
            $table->dropColumn(['source_report_ids', 'drafted_by', 'is_ai_generated']);
        });

        Schema::table('synthesis_reports', function (Blueprint $table) {
            $table->dropColumn(['group_ids', 'is_master_synthesis']);
        });
    }
};
