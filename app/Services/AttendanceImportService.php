<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ImportBatch;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class AttendanceImportService
{
    public const TEMPLATE_COLUMNS = ['employee_code', 'attendance_date', 'first_in', 'last_out', 'status'];

    public function validateBatch(ImportBatch $batch): void
    {
        $rows = $batch->rows()->orderBy('row_number')->get();
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $row) {
            $errors = $this->validateRow($row->raw_data);

            if ($errors === []) {
                $row->update(['status' => 'valid', 'errors' => null]);
                $successCount++;
            } else {
                $row->update(['status' => 'invalid', 'errors' => $errors]);
                $failedCount++;
            }
        }

        $batch->update([
            'total_rows' => $rows->count(),
            'success_rows' => $successCount,
            'failed_rows' => $failedCount,
            'status' => 'previewed',
        ]);
    }

    private function validateRow(array $data): array
    {
        $errors = [];
        $code = trim((string) ($data['employee_code'] ?? ''));

        if ($code === '') {
            $errors[] = 'employee_code is required';
        } elseif (! Employee::where('employee_code', $code)->exists()) {
            $errors[] = 'employee_code not found';
        }

        $date = $data['attendance_date'] ?? null;
        if (blank($date) || $this->parseDate($date) === null) {
            $errors[] = 'attendance_date is required and must be a valid date';
        }

        if (filled($data['status'] ?? null) && ! array_key_exists($data['status'], Attendance::STATUSES)) {
            $errors[] = 'status must be one of: '.implode(', ', array_keys(Attendance::STATUSES));
        }

        return $errors;
    }

    /**
     * Import valid rows. Rows that conflict with an existing attendance record for the
     * same employee/date are skipped unless $overwriteConflicts is true — attendance is
     * never silently overwritten.
     */
    public function importBatch(ImportBatch $batch, bool $overwriteConflicts = false): array
    {
        $rows = $batch->rows()->where('status', 'valid')->orderBy('row_number')->get();
        $imported = 0;
        $skippedConflicts = 0;

        foreach ($rows as $row) {
            $data = $row->raw_data;
            $employee = Employee::where('employee_code', $data['employee_code'])->first();
            $date = $this->parseDate($data['attendance_date']);

            $existing = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', $date->toDateString())
                ->first();

            if ($existing && ! $overwriteConflicts) {
                $row->update(['status' => 'invalid', 'errors' => ['Skipped: attendance already exists for this employee/date']]);
                $skippedConflicts++;

                continue;
            }

            $attendance = $existing ?? new Attendance([
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
            ]);

            $attendance->first_in = $data['first_in'] ?? $attendance->first_in;
            $attendance->last_out = $data['last_out'] ?? $attendance->last_out;
            $attendance->status = $data['status'] ?? $attendance->status ?? Attendance::STATUS_PRESENT;
            $attendance->source = 'import';
            $attendance->save();

            app(AttendanceService::class)->recalculate($attendance);

            $row->update(['status' => 'imported', 'imported_id' => $attendance->id]);
            $imported++;
        }

        $batch->update(['status' => 'imported', 'success_rows' => $imported]);

        return ['imported' => $imported, 'skipped_conflicts' => $skippedConflicts];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
