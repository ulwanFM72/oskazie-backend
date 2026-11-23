<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->restrictOnDelete();
            $table->unsignedSmallInteger('number_order');
            $table->string('chairman_name', 150);
            $table->string('chairman_class', 30);
            $table->string('vice_name', 150);
            $table->string('vice_class', 30);
            $table->string('photo_path')->nullable();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->text('work_program')->nullable();
            $table->text('profile')->nullable();
            $table->timestamps();

            $table->unique(['election_id', 'number_order']);
            $table->unique(['id', 'election_id']); // target FK komposit dari votes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
