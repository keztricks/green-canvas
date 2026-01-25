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
        Schema::table('exports', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->date('date_from')->nullable()->after('ward_id');
            $table->date('date_to')->nullable()->after('date_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
            $table->dropColumn(['ward_id', 'date_from', 'date_to']);
        });
    }
};
