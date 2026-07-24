<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop unique constraint by its actual PostgreSQL name
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'synthesis_reports_conversation_id_synthesis_id_unique'
                ) THEN
                    ALTER TABLE synthesis_reports
                    DROP CONSTRAINT synthesis_reports_conversation_id_synthesis_id_unique;
                END IF;
            END
            \$\$;
        ");

        // Drop foreign key constraint by its actual PostgreSQL name
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'synthesis_reports_synthesis_id_foreign'
                ) THEN
                    ALTER TABLE synthesis_reports
                    DROP CONSTRAINT synthesis_reports_synthesis_id_foreign;
                END IF;
            END
            \$\$;
        ");

        // Rename the table
        Schema::rename('synthesis_reports', 'master_syntheses');

        // Re-add foreign key on existing column (not create new column)
        DB::statement("
            ALTER TABLE master_syntheses
            ADD CONSTRAINT master_syntheses_synthesis_id_foreign
            FOREIGN KEY (synthesis_id) REFERENCES users (id) ON DELETE CASCADE
        ");

        // Re-add unique constraint
        DB::statement("
            ALTER TABLE master_syntheses
            ADD CONSTRAINT master_syntheses_conversation_id_synthesis_id_unique
            UNIQUE (conversation_id, synthesis_id)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraints on renamed table
        DB::statement("
            DO \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'master_syntheses_conversation_id_synthesis_id_unique'
                ) THEN
                    ALTER TABLE master_syntheses
                    DROP CONSTRAINT master_syntheses_conversation_id_synthesis_id_unique;
                END IF;

                IF EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'master_syntheses_synthesis_id_foreign'
                ) THEN
                    ALTER TABLE master_syntheses
                    DROP CONSTRAINT master_syntheses_synthesis_id_foreign;
                END IF;
            END
            \$\$;
        ");

        // Rename back
        Schema::rename('master_syntheses', 'synthesis_reports');

        // Re-add original constraints on existing column
        DB::statement("
            ALTER TABLE synthesis_reports
            ADD CONSTRAINT synthesis_reports_synthesis_id_foreign
            FOREIGN KEY (synthesis_id) REFERENCES users (id) ON DELETE CASCADE
        ");

        DB::statement("
            ALTER TABLE synthesis_reports
            ADD CONSTRAINT synthesis_reports_conversation_id_synthesis_id_unique
            UNIQUE (conversation_id, synthesis_id)
        ");
    }
};
