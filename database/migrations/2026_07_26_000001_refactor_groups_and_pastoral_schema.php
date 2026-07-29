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
        // 1. Add synthesis_id back to groups table
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'synthesis_id')) {
                $table->foreignId('synthesis_id')->nullable()->after('event_id')->constrained('users')->nullOnDelete();
            }
        });

        // 2. Drop synthesis_group_assignments if exists
        Schema::dropIfExists('synthesis_group_assignments');

        // 3. Create conversation_group pivot table
        if (!Schema::hasTable('conversation_group')) {
            Schema::create('conversation_group', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('group_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['conversation_id', 'group_id']);
            });
        }

        // 4. Update decision_drafts: add conversation_id, text fields
        Schema::table('decision_drafts', function (Blueprint $table) {
            if (!Schema::hasColumn('decision_drafts', 'conversation_id')) {
                $table->foreignId('conversation_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('decision_drafts', 'national_text')) {
                $table->text('national_text')->nullable();
            }
            if (!Schema::hasColumn('decision_drafts', 'diocesan_text')) {
                $table->text('diocesan_text')->nullable();
            }
            if (!Schema::hasColumn('decision_drafts', 'is_submitted')) {
                $table->boolean('is_submitted')->default(false);
            }
            if (!Schema::hasColumn('decision_drafts', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
        });

        // 5. Update decision_summaries: add conversation_id, level, etc.
        Schema::table('decision_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('decision_summaries', 'conversation_id')) {
                $table->foreignId('conversation_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('decision_summaries', 'level')) {
                $table->string('level')->default('national')->after('diocese_id');
            }
            if (!Schema::hasColumn('decision_summaries', 'summary_text')) {
                $table->text('summary_text')->nullable();
            }
            if (!Schema::hasColumn('decision_summaries', 'is_successful')) {
                $table->boolean('is_successful')->default(false);
            }
            if (!Schema::hasColumn('decision_summaries', 'generated_at')) {
                $table->timestamp('generated_at')->nullable();
            }
        });

        // 6. Recreate master_decisions table with proper columns
        Schema::dropIfExists('master_decisions');
        Schema::create('master_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level'); // 'national' or 'diocese'
            $table->text('decision_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'level', 'diocese_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_decisions');
        Schema::dropIfExists('conversation_group');
        
        Schema::table('decision_summaries', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['conversation_id', 'level', 'summary_text', 'is_successful', 'generated_at']);
        });

        Schema::table('decision_drafts', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['conversation_id', 'national_text', 'diocesan_text', 'is_submitted', 'submitted_at']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['synthesis_id']);
            $table->dropColumn(['synthesis_id']);
        });
    }
};
