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
            $table->dropForeign(['synthesis_id']);
            $table->dropColumn('synthesis_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_syntheses', function (Blueprint $table) {
            $table->unsignedBigInteger('synthesis_id')->nullable()->after('conversation_id');
            $table->foreign('synthesis_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }
};
