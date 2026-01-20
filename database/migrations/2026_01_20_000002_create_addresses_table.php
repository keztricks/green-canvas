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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->text('raw_address');
            $table->string('postcode', 8)->nullable();
            $table->string('house_number')->nullable();
            $table->string('street_name')->nullable();
            $table->string('street_norm')->nullable();
            $table->text('norm')->nullable();
            $table->foreignId('street_id')->nullable()->constrained('streets')->onDelete('set null');
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lon', 11, 8)->nullable();
            $table->string('status')->default('unvisited');
            $table->timestamp('last_contacted_at')->nullable();
            $table->uuid('current_volunteer')->nullable();
            $table->timestamps();

            $table->index(['postcode', 'house_number']);
            $table->index('street_norm');
            $table->index('street_id');

            $table->foreign('current_volunteer')->references('id')->on('volunteers')->onDelete('set null');
        });

        // Add fulltext index
        DB::statement('ALTER TABLE addresses ADD FULLTEXT INDEX addresses_norm_fulltext (norm)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
