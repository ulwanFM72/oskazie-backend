<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->dateTime('start_at');                       // UTC
            $table->dateTime('end_at');                         // UTC
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('results_published_at')->nullable();
            $table->json('final_summary')->nullable();          // dibekukan saat finalisasi
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE elections ADD CONSTRAINT elections_period_check CHECK (end_at > start_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
