<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    protected $fillable = [
        'election_id', 'number_order', 'chairman_name', 'chairman_class',
        'vice_name', 'vice_class', 'photo_path', 'vision', 'mission',
        'work_program', 'profile',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'number_order' => $this->number_order,
            'chairman_name' => $this->chairman_name,
            'chairman_class' => $this->chairman_class,
            'vice_name' => $this->vice_name,
            'vice_class' => $this->vice_class,
            'photo_url' => $this->photoUrl(),
            'vision' => $this->vision,
            'mission' => $this->mission,
            'work_program' => $this->work_program,
            'profile' => $this->profile,
        ];
    }
}
