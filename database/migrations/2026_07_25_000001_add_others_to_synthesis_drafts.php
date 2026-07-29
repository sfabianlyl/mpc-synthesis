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
        Schema::table('synthesis_drafts', function (Blueprint $table) {
            $table->text('other_text')->nullable()->after('proposals_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synthesis_drafts', function (Blueprint $table) {
            $table->dropColumn('other_text');
        });
    }
};
