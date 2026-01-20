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
        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            $table->string('street_norm')->unique();
            $table->string('display_name');
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('lock_until')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')->references('id')->on('volunteers')->onDelete('set null');
        });

        // Add fulltext index
        DB::statement('ALTER TABLE streets ADD FULLTEXT INDEX streets_street_norm_fulltext (street_norm)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streets');
    }
};
