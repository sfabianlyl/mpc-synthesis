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
            $table->dropColumn('submission_number');
        });

        Schema::table('synthesis_custom_queries', function (Blueprint $table) {
            $table->renameColumn('synthesis_report_id','synthesis_draft_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_syntheses', function (Blueprint $table) {
            $table->integer('submission_number')->nullable()->after('conversation_id');
        });
        Schema::table('synthesis_custom_queries', function (Blueprint $table) {
            $table->renameColumn('synthesis_draft_id','synthesis_report_id');
        });
    }
};
