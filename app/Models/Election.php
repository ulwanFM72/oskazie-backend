<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    protected $fillable = [
        'name', 'start_at', 'end_at', 'finalized_at',
        'results_published_at', 'final_summary', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'finalized_at' => 'datetime',
            'results_published_at' => 'datetime',
            'final_summary' => 'array',
        ];
    }

    /** Hanya satu pemilihan aktif: yang terbaru. */
    public static function current(): ?self
    {
        return static::query()->orderByDesc('id')->first();
    }

    /** Status dihitung dari waktu server (UTC), bukan disimpan. */
    public function status(?CarbonInterface $at = null): string
    {
        $at ??= now();

        if ($this->finalized_at !== null) {
            return 'final';
        }
        if ($at->lt($this->start_at)) {
            return 'belum_dimulai';
        }
        if ($at->lt($this->end_at)) {
            return 'berlangsung';
        }

        return 'selesai';
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function isVotingOpen(): bool
    {
        return $this->status() === 'berlangsung';
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function electionVoters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status(),
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'results_published_at' => $this->results_published_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
