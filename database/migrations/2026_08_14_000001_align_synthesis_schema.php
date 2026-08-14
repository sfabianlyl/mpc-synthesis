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
        // 1. Remove synthesis_id from groups table
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'synthesis_id')) {
                $table->dropForeign(['synthesis_id']);
                $table->dropColumn('synthesis_id');
            }
        });

        // 2. Drop conversation_members table if exists
        Schema::dropIfExists('conversation_members');

        // 3. Update consensus_notes table to use response_id instead of group_id
        Schema::table('consensus_notes', function (Blueprint $table) {
            if (Schema::hasColumn('consensus_notes', 'group_id')) {
                $table->dropForeign(['group_id']);
                $table->dropUnique(['conversation_id', 'user_id', 'group_id']);
                $table->dropColumn('group_id');
            }

            if (!Schema::hasColumn('consensus_notes', 'response_id')) {
                $table->foreignId('response_id')->after('user_id')->constrained('responses')->cascadeOnDelete();
                $table->unique(['conversation_id', 'user_id', 'response_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consensus_notes', function (Blueprint $table) {
            if (Schema::hasColumn('consensus_notes', 'response_id')) {
                $table->dropForeign(['response_id']);
                $table->dropUnique(['conversation_id', 'user_id', 'response_id']);
                $table->dropColumn('response_id');
            }

            if (!Schema::hasColumn('consensus_notes', 'group_id')) {
                $table->foreignId('group_id')->after('user_id')->constrained('groups')->cascadeOnDelete();
                $table->unique(['conversation_id', 'user_id', 'group_id']);
            }
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'synthesis_id')) {
                $table->foreignId('synthesis_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }
};
