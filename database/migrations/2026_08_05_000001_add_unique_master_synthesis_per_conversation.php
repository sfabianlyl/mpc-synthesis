<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enforce at most one master synthesis record per conversation (where is_master_synthesis = true)
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS master_syntheses_one_per_conversation
            ON master_syntheses (conversation_id)
            WHERE is_master_synthesis = true
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS master_syntheses_one_per_conversation");
    }
};
