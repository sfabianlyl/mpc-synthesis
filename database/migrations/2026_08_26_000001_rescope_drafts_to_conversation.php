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
        // Drop old event-scoped drafts tables
        Schema::dropIfExists('national_decision_drafts');
        Schema::dropIfExists('diocese_decision_drafts');

        // Create conversation-scoped national_decision_drafts
        Schema::create('national_decision_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->nullOnDelete();
            $table->text('draft_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Each member submits one national draft per conversation
            $table->unique(['conversation_id', 'user_id'], 'uq_nat_draft_conv_user');
        });

        // Create conversation-scoped diocese_decision_drafts
        Schema::create('diocese_decision_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->nullOnDelete();
            $table->text('draft_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            // Each member submits one diocese draft per conversation
            $table->unique(['conversation_id', 'user_id'], 'uq_dioc_draft_conv_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diocese_decision_drafts');
        Schema::dropIfExists('national_decision_drafts');

        // Recreate event-scoped tables for rollback
        Schema::create('national_decision_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->nullOnDelete();
            $table->text('draft_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('diocese_decision_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->nullOnDelete();
            $table->text('draft_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }
};
