<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['siswa', 'guru']);
            $table->string('name', 150);
            $table->char('nis_hash', 64)->nullable()->unique();   // siswa: HMAC-SHA256(NIS, pepper)
            $table->string('login_key', 150)->nullable()->unique(); // guru: nama lengkap ternormalisasi
            $table->string('class_name', 30)->nullable();           // siswa
            $table->string('unit', 60)->nullable();                 // guru/staf
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'class_name']);
            $table->index(['type', 'unit']);
        });

        DB::statement("ALTER TABLE voters ADD CONSTRAINT voters_identity_check CHECK (
            (type = 'siswa' AND nis_hash IS NOT NULL AND login_key IS NULL)
            OR (type = 'guru' AND login_key IS NOT NULL AND nis_hash IS NULL)
        )");
    }

    public function down(): void
    {
        Schema::dropIfExists('voters');
    }
};
