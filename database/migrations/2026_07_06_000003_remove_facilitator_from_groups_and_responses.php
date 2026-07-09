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
        // Drop facilitator_id from groups table (only if constraint exists)
        Schema::table('groups', function (Blueprint $table) {
            // Check if foreign key exists before dropping
            $exists = DB::select("
                SELECT 1 FROM information_schema.table_constraints
                WHERE constraint_name = 'groups_facilitator_id_foreign'
                AND table_name = 'groups'
            ");

            if (!empty($exists)) {
                $table->dropForeign(['facilitator_id']);
            }

            // Only drop column if it exists
            $columns = DB::select("
                SELECT column_name FROM information_schema.columns
                WHERE table_name = 'groups' AND column_name = 'facilitator_id'
            ");

            if (!empty($columns)) {
                $table->dropColumn('facilitator_id');
            }
        });

        // Drop facilitator_id from responses table (only if constraint exists)
        Schema::table('responses', function (Blueprint $table) {
            // Check if foreign key exists before dropping
            $exists = DB::select("
                SELECT 1 FROM information_schema.table_constraints
                WHERE constraint_name = 'responses_facilitator_id_foreign'
                AND table_name = 'responses'
            ");

            if (!empty($exists)) {
                $table->dropForeign(['facilitator_id']);
            }

            // Only drop column if it exists
            $columns = DB::select("
                SELECT column_name FROM information_schema.columns
                WHERE table_name = 'responses' AND column_name = 'facilitator_id'
            ");

            if (!empty($columns)) {
                $table->dropColumn('facilitator_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
