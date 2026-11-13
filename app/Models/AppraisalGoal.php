<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalGoal extends Model
{
    protected $fillable = [
        'appraisal_id', 'title', 'description', 'target', 'weightage',
        'self_rating', 'self_comments', 'manager_rating', 'manager_comments', 'sequence',
    ];

    protected function casts(): array
    {
        return ['weightage' => 'integer', 'self_rating' => 'integer', 'manager_rating' => 'integer'];
    }

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }
}
