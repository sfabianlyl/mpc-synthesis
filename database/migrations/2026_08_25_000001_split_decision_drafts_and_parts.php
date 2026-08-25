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
        // Drop obsolete tables
        Schema::dropIfExists('decision_drafts');
        Schema::dropIfExists('decision_parts');

        // Create diocese_decision_parts table
        Schema::create('diocese_decision_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('diocesan_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        // Create national_decision_parts table
        Schema::create('national_decision_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('national_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('national_decision_parts');
        Schema::dropIfExists('diocese_decision_parts');

        // Recreate decision_drafts table (for rollback)
        Schema::create('decision_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diocese_id')->nullable()->constrained()->nullOnDelete();
            $table->text('national_text')->nullable();
            $table->text('diocesan_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        // Recreate decision_parts table (for rollback)
        Schema::create('decision_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('national_text')->nullable();
            $table->text('diocesan_text')->nullable();
            $table->text('consolidated_text')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }
};
