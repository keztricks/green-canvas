<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_ward_export_schedules');

        DB::table('feature_flags')->where('key', 'export_email_schedules')->delete();
    }

    public function down(): void
    {
        Schema::create('user_ward_export_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ward_id')->constrained()->onDelete('cascade');
            $table->enum('frequency', ['none', 'daily', 'weekly'])->default('none');
            $table->timestamps();

            $table->unique(['user_id', 'ward_id']);
        });
    }
};
