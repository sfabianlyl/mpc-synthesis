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
        // 1. Ensure unique constraint on decision_drafts: conversation_id + user_id
        Schema::table('decision_drafts', function (Blueprint $table) {
            if (!Schema::hasColumn('decision_drafts', 'conversation_id')) {
                $table->foreignId('conversation_id')->after('id')->constrained()->cascadeOnDelete();
            }
            $table->unique(['conversation_id', 'user_id'], 'uq_decision_drafts_conv_user');
        });

        // 2. Re-create master_decisions table with event-scoped schema
        Schema::dropIfExists('master_decisions');
        Schema::create('master_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('master_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decision_drafts', function (Blueprint $table) {
            $table->dropUnique('uq_decision_drafts_conv_user');
        });

        Schema::dropIfExists('master_decisions');
    }
};
