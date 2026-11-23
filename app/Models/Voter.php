<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Voter extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['type', 'name', 'nis_hash', 'login_key', 'class_name', 'unit', 'is_active'];

    protected $hidden = ['nis_hash', 'login_key'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
