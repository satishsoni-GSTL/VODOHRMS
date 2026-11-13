<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeBankDetail;
use App\Models\EmployeeDocument;
use App\Models\EmployeeStatutoryDetail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The employee's own profile: what "complete" means, and the self-service update used by
 * both My Profile (logged-in employee) and the pre-joining portal (new hire, before day one).
 *
 * Self-service rules:
 *  - MODE_PREJOINING: the new hire may enter or change everything until they submit.
 *  - MODE_SELF: contact, address and emergency details can always be updated; identity
 *    (name, date of birth, gender), statutory IDs and bank details can only be *filled in*
 *    when missing — changing an existing value goes through HR.
 */
class EmployeeProfileService
{
    public const MODE_SELF = 'self';

    public const MODE_PREJOINING = 'prejoining';

    /** Fields an employee may fill once but not change afterwards (HR changes them). */
    public const LOCKED_ONCE_SET = ['first_name', 'middle_name', 'last_name', 'dob', 'gender', 'pan', 'aadhaar', 'uan'];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];

    public const MARITAL = ['single' => 'Single', 'married' => 'Married', 'other' => 'Other'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    private const ADDRESS_PARTS = ['line1', 'line2', 'city', 'state', 'pincode'];

    // ---- Completion -------------------------------------------------------------------

    /**
     * @return array{percent: int, done: int, total: int, sections: array<string, array{label: string, done: int, total: int, items: array<int, array{key: string, label: string, done: bool}>}>}
     */
    public function completion(Employee $employee): array
    {
        $employee->loadMissing(['statutoryDetail', 'bankDetails', 'documents']);
        $statutory = $employee->statutoryDetail;
        $bank = $this->primaryBank($employee);
        $docTypes = $employee->documents->pluck('document_type')->unique()->all();

        $address = fn (?array $a) => filled($a['line1'] ?? null) && filled($a['city'] ?? null) && filled($a['state'] ?? null) && filled($a['pincode'] ?? null);

        $sections = [
            'personal' => ['Personal details', [
                'first_name' => ['First name', filled($employee->first_name)],
                'last_name' => ['Last name', filled($employee->last_name)],
                'dob' => ['Date of birth', filled($employee->dob)],
                'gender' => ['Gender', filled($employee->gender)],
                'marital_status' => ['Marital status', filled($employee->marital_status)],
                'blood_group' => ['Blood group', filled($employee->blood_group)],
            ]],
            'contact' => ['Contact', [
                'personal_mobile' => ['Personal mobile', filled($employee->personal_mobile)],
                'personal_email' => ['Personal email', filled($employee->personal_email)],
            ]],
            'address' => ['Address', [
                'current_address' => ['Current address', $address($employee->current_address)],
                'permanent_address' => ['Permanent address', $address($employee->permanent_address)],
            ]],
            'emergency' => ['Emergency contact', [
                'emergency_contact_name' => ['Contact name', filled($employee->emergency_contact_name)],
                'emergency_contact_relation' => ['Relationship', filled($employee->emergency_contact_relation)],
                'emergency_contact_phone' => ['Contact phone', filled($employee->emergency_contact_phone)],
            ]],
            'statutory' => ['Statutory IDs', [
                'pan' => ['PAN', filled($statutory?->pan)],
                'aadhaar' => ['Aadhaar', filled($statutory?->aadhaar)],
            ]],
            'bank' => ['Bank account', [
                'bank' => ['Salary bank account', $bank !== null && filled($bank->account_number) && filled($bank->ifsc)],
            ]],
            'documents' => ['Documents', collect(EmployeeDocument::REQUIRED_TYPES)
                ->mapWithKeys(fn ($label, $type) => ["doc:{$type}" => [$label, in_array($type, $docTypes, true)]])
                ->all()],
        ];

        $out = [];
        $done = 0;
        $total = 0;

        foreach ($sections as $key => [$label, $items]) {
            $list = collect($items)->map(fn ($v, $k) => ['key' => $k, 'label' => $v[0], 'done' => (bool) $v[1]])->values()->all();
            $sectionDone = collect($list)->where('done', true)->count();
            $out[$key] = ['label' => $label, 'done' => $sectionDone, 'total' => count($list), 'items' => $list];
            $done += $sectionDone;
            $total += count($list);
        }

        return ['percent' => $total ? (int) floor($done / $total * 100) : 100, 'done' => $done, 'total' => $total, 'sections' => $out];
    }

    /** @return array<int, string> labels of missing non-document items */
    public function missingDetails(Employee $employee): array
    {
        return collect($this->completion($employee)['sections'])
            ->except('documents')
            ->flatMap(fn ($s) => collect($s['items'])->where('done', false)->pluck('label'))
            ->values()->all();
    }

    public function primaryBank(Employee $employee): ?EmployeeBankDetail
    {
        $banks = $employee->relationLoaded('bankDetails') ? $employee->bankDetails : $employee->bankDetails()->get();

        return $banks->firstWhere('is_primary', true) ?? $banks->first();
    }

    /** Form state for the self-service forms (both My Profile and the portal). */
    public function formData(Employee $employee): array
    {
        $statutory = $employee->statutoryDetail;
        $bank = $this->primaryBank($employee);

        return [
            ...$employee->only([
                'first_name', 'middle_name', 'last_name', 'gender', 'marital_status', 'blood_group',
                'personal_mobile', 'alternate_mobile', 'personal_email',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            ]),
            'dob' => $employee->dob?->toDateString(),
            'current_address' => array_merge(array_fill_keys(self::ADDRESS_PARTS, null), $employee->current_address ?? []),
            'permanent_address' => array_merge(array_fill_keys(self::ADDRESS_PARTS, null), $employee->permanent_address ?? []),
            'pan' => $statutory?->pan,
            'aadhaar' => $statutory?->aadhaar,
            'uan' => $statutory?->uan,
            'bank' => [
                'account_holder_name' => $bank?->account_holder_name,
                'bank_name' => $bank?->bank_name,
                'account_number' => $bank?->account_number,
                'ifsc' => $bank?->ifsc,
                'branch_name' => $bank?->branch_name,
            ],
        ];
    }

    /** Whether a self-service user may change this field right now. */
    public function isEditable(Employee $employee, string $field, string $mode): bool
    {
        if ($mode === self::MODE_PREJOINING) {
            return true;
        }

        if ($field === 'bank') {
            $bank = $this->primaryBank($employee);

            return ! ($bank && filled($bank->account_number));
        }

        if (! in_array($field, self::LOCKED_ONCE_SET, true)) {
            return true;
        }

        $value = in_array($field, ['pan', 'aadhaar', 'uan'], true) ? $employee->statutoryDetail?->{$field} : $employee->{$field};

        return blank($value);
    }

    // ---- Update -----------------------------------------------------------------------

    /**
     * Apply a self-service update. Only fields the user may change in this mode are applied;
     * blanks never wipe existing data. Formats are validated (PAN, Aadhaar, IFSC, phones...).
     */
    public function update(Employee $employee, array $data, string $mode): Employee
    {
        $this->validate($data);

        DB::transaction(function () use ($employee, $data, $mode) {
            $employeeFields = [
                'first_name', 'middle_name', 'last_name', 'dob', 'gender', 'marital_status', 'blood_group',
                'personal_mobile', 'alternate_mobile', 'personal_email',
                'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            ];

            $changes = [];

            foreach ($employeeFields as $field) {
                if (array_key_exists($field, $data) && filled($data[$field]) && $this->isEditable($employee, $field, $mode)) {
                    $changes[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }

            foreach (['current_address', 'permanent_address'] as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    $incoming = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($data[$field], array_flip(self::ADDRESS_PARTS))), 'filled');

                    if ($incoming !== []) {
                        $changes[$field] = array_merge($employee->{$field} ?? [], $incoming);
                    }
                }
            }

            if ($changes !== []) {
                $employee->update($changes);
            }

            $statutory = [];

            foreach (['pan', 'aadhaar', 'uan'] as $field) {
                if (filled($data[$field] ?? null) && $this->isEditable($employee, $field, $mode)) {
                    $statutory[$field] = strtoupper(preg_replace('/\s+/', '', $data[$field]));
                }
            }

            if ($statutory !== []) {
                EmployeeStatutoryDetail::updateOrCreate(['employee_id' => $employee->id], $statutory);
            }

            $bank = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data['bank'] ?? []), 'filled');

            if ($bank !== [] && $this->isEditable($employee, 'bank', $mode)) {
                if (isset($bank['ifsc'])) {
                    $bank['ifsc'] = strtoupper($bank['ifsc']);
                }

                $existing = $this->primaryBank($employee);
                $existing
                    ? $existing->update(array_intersect_key($bank, array_flip(['account_holder_name', 'bank_name', 'account_number', 'ifsc', 'branch_name'])))
                    : $employee->bankDetails()->create($bank + ['is_primary' => true]);
            }
        });

        $employee->unsetRelation('statutoryDetail')->unsetRelation('bankDetails');

        return $employee->fresh();
    }

    private function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'dob' => ['nullable', 'date', 'before:-15 years'],
            'gender' => ['nullable', Rule::in(array_keys(self::GENDERS))],
            'marital_status' => ['nullable', Rule::in(array_keys(self::MARITAL))],
            'blood_group' => ['nullable', Rule::in(self::BLOOD_GROUPS)],
            'personal_mobile' => ['nullable', 'regex:/^(\+91[\s-]?)?[6-9]\d{9}$/'],
            'alternate_mobile' => ['nullable', 'regex:/^(\+91[\s-]?)?[6-9]\d{9}$/'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'regex:/^(\+91[\s-]?)?[6-9]\d{9}$/'],
            'current_address.pincode' => ['nullable', 'digits:6'],
            'permanent_address.pincode' => ['nullable', 'digits:6'],
            'pan' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'aadhaar' => ['nullable', 'regex:/^\d{4}\s?\d{4}\s?\d{4}$/'],
            'uan' => ['nullable', 'digits:12'],
            'bank.account_number' => ['nullable', 'regex:/^\d{6,20}$/'],
            'bank.ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'bank.account_holder_name' => ['nullable', 'string', 'max:150'],
            'bank.bank_name' => ['nullable', 'string', 'max:150'],
        ], [
            'dob.before' => 'Please enter a valid date of birth.',
            'personal_mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'alternate_mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'emergency_contact_phone.regex' => 'Enter a valid 10-digit mobile number.',
            'pan.regex' => 'PAN should look like ABCDE1234F.',
            'aadhaar.regex' => 'Aadhaar should be 12 digits.',
            'uan.digits' => 'UAN should be 12 digits.',
            'bank.account_number.regex' => 'Account number should be 6 to 20 digits.',
            'bank.ifsc.regex' => 'IFSC should look like HDFC0001234.',
            '*.pincode.digits' => 'PIN code should be 6 digits.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    // ---- Documents ----------------------------------------------------------------------

    public function addDocument(Employee $employee, string $type, UploadedFile $file, string $source, ?User $by = null): EmployeeDocument
    {
        if (! array_key_exists($type, EmployeeDocument::types())) {
            throw ValidationException::withMessages(['document_type' => 'Unknown document type.']);
        }

        $path = $file->store("employee-documents/{$employee->id}", 'local');

        return $this->recordDocument($employee, $type, $path, $file->getClientOriginalName(), $source, $by);
    }

    /** Register a file already stored on the local disk (e.g. by a Filament upload). */
    public function recordDocument(Employee $employee, string $type, string $path, ?string $name, string $source, ?User $by = null): EmployeeDocument
    {
        return $employee->documents()->create([
            'document_type' => $type,
            'file_path' => $path,
            'file_name' => $name ?: basename($path),
            'source' => $source,
            'uploaded_by' => $by?->id,
            'uploaded_at' => now(),
            'is_verified' => false,
        ]);
    }

    /** An unverified upload can be removed by whoever may self-serve; verified ones only by HR. */
    public function deleteDocument(EmployeeDocument $document): void
    {
        if ($document->is_verified) {
            throw ValidationException::withMessages(['document' => 'This document has been verified by HR and can no longer be removed.']);
        }

        Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }
}
