<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mencatat SIAPA sudah memilih. Tidak ada kolom pilihan di tabel ini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vote_participations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('voter_id');
            $table->dateTime('voted_at'); // detik dinolkan oleh aplikasi (presisi menit)

            $table->unique(['election_id', 'voter_id']); // satu pemilih, satu suara

            $table->foreign(['election_id', 'voter_id'])
                ->references(['election_id', 'voter_id'])
                ->on('election_voters')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_participations');
    }
};
