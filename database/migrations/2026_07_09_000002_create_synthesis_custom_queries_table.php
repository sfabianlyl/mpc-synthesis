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
        Schema::create('synthesis_custom_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('synthesis_report_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');               // The user's custom prompt
            $table->json('response');             // LLM response as JSON array of bullet points
            $table->string('provider')->nullable(); // Which LLM provider was used
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('synthesis_custom_queries');
    }
};
