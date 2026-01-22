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
        // SQLite doesn't support modifying columns directly, so we need to recreate the table
        Schema::table('knock_results', function (Blueprint $table) {
            $table->string('response_new')->after('address_id');
        });

        // Copy existing data with mapping
        DB::table('knock_results')->get()->each(function ($result) {
            $mapping = [
                'support' => 'green',
                'against' => 'conservative',
            ];
            $newResponse = $mapping[$result->response] ?? $result->response;
            DB::table('knock_results')
                ->where('id', $result->id)
                ->update(['response_new' => $newResponse]);
        });

        Schema::table('knock_results', function (Blueprint $table) {
            $table->dropColumn('response');
        });

        Schema::table('knock_results', function (Blueprint $table) {
            $table->renameColumn('response_new', 'response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knock_results', function (Blueprint $table) {
            $table->string('response_old')->after('address_id');
        });

        DB::table('knock_results')->get()->each(function ($result) {
            $mapping = [
                'green' => 'support',
                'conservative' => 'against',
                'labour' => 'against',
                'lib_dem' => 'against',
                'reform' => 'against',
            ];
            $oldResponse = $mapping[$result->response] ?? $result->response;
            DB::table('knock_results')
                ->where('id', $result->id)
                ->update(['response_old' => $oldResponse]);
        });

        Schema::table('knock_results', function (Blueprint $table) {
            $table->dropColumn('response');
        });

        Schema::table('knock_results', function (Blueprint $table) {
            $table->renameColumn('response_old', 'response');
        });
    }
};
