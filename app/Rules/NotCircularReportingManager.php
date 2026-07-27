<?php

namespace App\Rules;

use App\Services\EmployeeHierarchyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCircularReportingManager implements ValidationRule
{
    public function __construct(private readonly ?int $employeeId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $service = app(EmployeeHierarchyService::class);

        if ($service->wouldCreateCircularReference($this->employeeId, (int) $value)) {
            $fail('This reporting manager assignment would create a circular reporting hierarchy.');
        }
    }
}
