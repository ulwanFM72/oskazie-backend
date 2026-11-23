<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mencatat PILIHAN. Tanpa identitas pemilih, tanpa timestamp, ID acak (UUID v4).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('candidate_id');

            $table->index(['election_id', 'candidate_id']);

            $table->foreign('election_id')->references('id')->on('elections')->restrictOnDelete();
            $table->foreign(['candidate_id', 'election_id'])
                ->references(['id', 'election_id'])
                ->on('candidates')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
