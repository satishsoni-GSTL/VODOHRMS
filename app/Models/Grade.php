<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use HasActiveScope, SoftDeletes;

    protected $fillable = ['name', 'code', 'level', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
