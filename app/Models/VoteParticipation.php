<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mencatat SIAPA sudah memilih. Tidak ada kolom pilihan. */
class VoteParticipation extends Model
{
    public $timestamps = false;

    protected $fillable = ['election_id', 'voter_id', 'voted_at'];

    protected function casts(): array
    {
        return ['voted_at' => 'datetime'];
    }
}
