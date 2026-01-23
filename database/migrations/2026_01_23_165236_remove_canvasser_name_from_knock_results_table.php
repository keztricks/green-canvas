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
            $table->dropColumn('canvasser_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knock_results', function (Blueprint $table) {
            $table->string('canvasser_name')->nullable();
        });
    }
};
