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
        Schema::create('knock_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_id')->constrained()->onDelete('cascade');
            $table->enum('response', ['not_home', 'support', 'against', 'undecided', 'refused', 'moved']);
            $table->text('notes')->nullable();
            $table->string('canvasser_name')->nullable();
            $table->timestamp('knocked_at');
            $table->timestamps();
            
            $table->index('address_id');
            $table->index('knocked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knock_results');
    }
};
