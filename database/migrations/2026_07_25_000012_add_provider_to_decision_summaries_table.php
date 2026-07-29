<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('decision_summaries', function (Blueprint $table) {
            // Allow diocese_id to be nullable for national-level summaries
            $table->dropForeign(['diocese_id']);
            $table->foreignId('diocese_id')
                ->nullable()
                ->change()
                ->nullOnDelete();

            $table->string('provider')->nullable()->after('diocesan_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decision_summaries', function (Blueprint $table) {
            $table->dropColumn('provider');

            $table->dropForeign(['diocese_id']);
            $table->foreignId('diocese_id')
                ->nullable(false)
                ->change()
                ->cascadeOnDelete();
        });
    }
};
