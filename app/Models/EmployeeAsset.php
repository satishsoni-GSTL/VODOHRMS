<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAsset extends Model
{
    protected $fillable = ['employee_id', 'asset_type', 'asset_tag', 'allocated_on', 'returned_on'];

    protected function casts(): array
    {
        return [
            'allocated_on' => 'date:Y-m-d',
            'returned_on' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
