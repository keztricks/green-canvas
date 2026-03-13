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
        Schema::table('knock_results', function (Blueprint $table) {
            $table->enum('turnout_likelihood', ['wont', 'might', 'will'])->nullable()->after('vote_likelihood');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knock_results', function (Blueprint $table) {
            $table->dropColumn('turnout_likelihood');
        });
    }
};
