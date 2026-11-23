<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionVoter extends Model
{
    protected $fillable = [
        'election_id', 'voter_id', 'voter_type', 'class_name', 'unit',
        'attendance_status', 'absence_reason', 'absence_note',
        'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }
}
