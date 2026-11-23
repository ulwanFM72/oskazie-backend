<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_voters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->restrictOnDelete();
            $table->foreignId('voter_id')->constrained('voters')->restrictOnDelete();

            // salinan data saat snapshot DPT
            $table->enum('voter_type', ['siswa', 'guru']);
            $table->string('class_name', 30)->nullable();
            $table->string('unit', 60)->nullable();

            $table->enum('attendance_status', ['belum_dicatat', 'hadir', 'tidak_hadir'])->default('belum_dicatat');
            $table->enum('absence_reason', ['sakit', 'izin', 'dispensasi', 'pkl', 'lainnya', 'tanpa_keterangan'])->nullable();
            $table->text('absence_note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['election_id', 'voter_id']);
            $table->index(['election_id', 'attendance_status']);
            $table->index(['election_id', 'class_name']);
        });

        // alasan wajib ada jika dan hanya jika status = tidak_hadir
        DB::statement("ALTER TABLE election_voters ADD CONSTRAINT election_voters_absence_check CHECK (
            (attendance_status = 'tidak_hadir') = (absence_reason IS NOT NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('election_voters');
    }
};
