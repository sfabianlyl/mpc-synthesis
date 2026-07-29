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
        Schema::table('master_syntheses', function (Blueprint $table) {
            $table->dropColumn([
                'ai_convergence_text',
                'ai_consideration_text',
                'ai_proposals_text',
                'ai_other_text',
                'ai_source_group_ids',
                'ai_generated_at',
                'llm_generated',
                'is_llm_assisted',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_syntheses', function (Blueprint $table) {
            $table->text('ai_convergence_text')->nullable();
            $table->text('ai_consideration_text')->nullable();
            $table->text('ai_proposals_text')->nullable();
            $table->text('ai_other_text')->nullable();
            $table->json('ai_source_group_ids')->nullable();
            $table->timestamp('ai_generated_at')->nullable();
            $table->boolean('llm_generated')->default(false);
            $table->boolean('is_llm_assisted')->default(false);
        });
    }
};
