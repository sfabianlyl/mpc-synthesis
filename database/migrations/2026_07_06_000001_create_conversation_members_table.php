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
        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        // Data migration: Copy existing synthesis assignments from groups.synthesis_id
        // to the new conversation_members pivot table.
        $existingAssignments = DB::table('groups')
            ->whereNotNull('synthesis_id')
            ->select('synthesis_id', 'id')
            ->get();

        foreach ($existingAssignments as $assignment) {
            // Find conversations that have responses from this group
            $conversationIds = DB::table('responses')
                ->where('group_id', $assignment->id)
                ->distinct()
                ->pluck('conversation_id');

            foreach ($conversationIds as $conversationId) {
                DB::table('conversation_members')->updateOrCreate([
                    'conversation_id' => $conversationId,
                    'user_id' => $assignment->synthesis_id,
                ], []);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
    }
};
