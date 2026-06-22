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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('planning'); // planning, active, completed
            $table->timestamps();
        });

        // Add event_id to conversations table
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Add event_id to final_documents table
        Schema::table('final_documents', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('final_documents', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });

        Schema::dropIfExists('events');
    }
};
