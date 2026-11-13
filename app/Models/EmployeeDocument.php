<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    /** Documents every employee must have on file (count toward profile completion). */
    public const REQUIRED_TYPES = [
        'pan_card' => 'PAN card',
        'aadhaar_card' => 'Aadhaar card',
        'address_proof' => 'Address proof',
        'education_certificate' => 'Highest education certificate',
        'bank_proof' => 'Cancelled cheque / bank passbook',
    ];

    public const OPTIONAL_TYPES = [
        'relieving_letter' => 'Relieving / experience letter (previous employer)',
        'payslips' => 'Last 3 months payslips (previous employer)',
        'passport' => 'Passport',
        'other' => 'Other',
    ];

    public const SOURCES = ['hr' => 'HR', 'self' => 'Employee', 'prejoining' => 'Pre-joining portal'];

    protected $fillable = [
        'employee_id', 'document_type', 'file_path', 'file_name', 'source',
        'uploaded_by', 'uploaded_at', 'is_verified', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    public static function types(): array
    {
        return self::REQUIRED_TYPES + self::OPTIONAL_TYPES;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->document_type] ?? ucwords(str_replace('_', ' ', $this->document_type));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
