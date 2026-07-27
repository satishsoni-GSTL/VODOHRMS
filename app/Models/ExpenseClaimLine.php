<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseClaimLine extends Model
{
    protected $fillable = [
        'expense_claim_id', 'category_id', 'expense_date', 'requested_amount', 'approved_amount',
        'description', 'vendor', 'bill_number', 'payment_mode', 'receipt_path',
    ];

    protected function casts(): array
    {
        return ['expense_date' => 'date:Y-m-d'];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }
}
