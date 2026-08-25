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
        Schema::table('synthesis_summaries', function (Blueprint $table) {
            $table->unsignedInteger('submitted_drafts_count')->nullable()->default(0)->after('other_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synthesis_summaries', function (Blueprint $table) {
            $table->dropColumn('submitted_drafts_count');
        });
    }
};
