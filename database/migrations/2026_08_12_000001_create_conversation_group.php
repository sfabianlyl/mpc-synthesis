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
        Schema::create('conversation_groups', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys linking to the conversations and groups tables
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            
            $table->timestamps();

            // Prevents assigning the same group to the same conversation multiple times
            $table->unique(['conversation_id', 'group_id']);
        });
    
        Schema::create('conversation_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_group_id')->constrained()->cascadeOnDelete();
            
            // This dictates if they can perform the action, or just view.
            // Defaulting to false means they are a "viewer" by default.
            $table->boolean('is_synthesizer')->default(false); 
            
            $table->timestamps();
            
            // Prevent duplicate assignments
            $table->unique(['user_id', 'conversation_group_id']); 
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign('responses_conversation_id_foreign');
            $table->dropColumn('group_id');
            $table->renameColumn('conversation_id','conversation_group_id');

            $table->foreign('conversation_group_id')
                  ->references('id')
                  ->on('conversation_groups')
                  ->onDelete('cascade'); // Adjust this behavior if you prefer something other than cascade
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_groups');
        Schema::dropIfExists('conversation_group_user');
    }
};
