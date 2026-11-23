<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mencatat PILIHAN. Tanpa identitas, tanpa timestamp.
 * ID = UUID v4 acak (Str::uuid()), BUKAN UUID v7 yang berurutan menurut waktu.
 */
class Vote extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['election_id', 'candidate_id'];

    protected static function booted(): void
    {
        static::creating(function (Vote $vote) {
            $vote->id = (string) Str::uuid();
        });
    }
}
