<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasActiveScope;

    protected $fillable = ['name', 'code', 'requires_bill', 'requires_project', 'gl_code', 'is_active'];

    protected function casts(): array
    {
        return [
            'requires_bill' => 'boolean',
            'requires_project' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
