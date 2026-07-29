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
        Schema::create('synthesis_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->text('convergence_summary')->nullable();
            $table->text('consideration_summary')->nullable();
            $table->text('proposals_summary')->nullable();
            $table->text('other_summary')->nullable();
            $table->string('status')->default('draft'); // draft, completed
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synthesis_summaries');
    }
};
