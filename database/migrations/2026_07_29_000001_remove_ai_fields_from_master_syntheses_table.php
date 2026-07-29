<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columns = [
            'ai_convergence_text',
            'ai_consideration_text',
            'ai_proposals_text',
            'ai_source_group_ids',
            'ai_generated_at',
            'llm_generated',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('master_syntheses', $column)) {
                Schema::table('master_syntheses', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_syntheses', function (Blueprint $table) {
            if (!Schema::hasColumn('master_syntheses', 'ai_convergence_text')) {
                $table->text('ai_convergence_text')->nullable();
            }
            if (!Schema::hasColumn('master_syntheses', 'ai_consideration_text')) {
                $table->text('ai_consideration_text')->nullable();
            }
            if (!Schema::hasColumn('master_syntheses', 'ai_proposals_text')) {
                $table->text('ai_proposals_text')->nullable();
            }
            if (!Schema::hasColumn('master_syntheses', 'ai_source_group_ids')) {
                $table->json('ai_source_group_ids')->nullable();
            }
            if (!Schema::hasColumn('master_syntheses', 'ai_generated_at')) {
                $table->timestamp('ai_generated_at')->nullable();
            }
            if (!Schema::hasColumn('master_syntheses', 'llm_generated')) {
                $table->boolean('llm_generated')->default(false);
            }
        });
    }
};
