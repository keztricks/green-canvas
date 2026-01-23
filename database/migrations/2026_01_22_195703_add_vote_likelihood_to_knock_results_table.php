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
            $table->integer('vote_likelihood')->nullable()->after('response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knock_results', function (Blueprint $table) {
            $table->dropColumn('vote_likelihood');
        });
    }
};
