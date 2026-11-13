@extends('prejoining.layout')

@section('title', 'Your joining details')

@section('topbar-actions')
    <form method="POST" action="{{ route('prejoining.sign-out', $token) }}">
        @csrf
        <button type="submit" class="btn-link">Sign out</button>
    </form>
@endsection

@php
    $v = fn (string $key) => old($key, data_get($data, $key));
    $ro = $editable ? '' : 'readonly';
    $sections = $completion['sections'];
    $sectionPill = function (string $key) use ($sections) {
        $s = $sections[$key];
        $cls = $s['done'] === $s['total'] ? 'pill pill-ok' : 'pill pill-warn';

        return "<span class=\"{$cls}\">{$s['done']}/{$s['total']}</span>";
    };
    $docsByType = $employee->documents->groupBy('document_type');
@endphp

@section('content')
    <div class="card stack">
        <div class="row space-between">
            <div>
                <h1>Hi {{ $employee->first_name }}, welcome to {{ $employee->company?->name ?? config('app.name') }}</h1>
                <p class="muted" style="margin:4px 0 0">
                    @if ($employee->date_of_joining) Joining on <strong>{{ $employee->date_of_joining->format('d M Y') }}</strong>. @endif
                    Fill in your details and upload your documents. Fields marked <span style="color:var(--danger)">*</span> are required.
                </p>
            </div>
            <div style="min-width:180px">
                <div class="row space-between"><span class="muted">Completed</span><strong>{{ $completion['percent'] }}%</strong></div>
                <div class="progress"><span style="width: {{ $completion['percent'] }}%"></span></div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-ok">{{ session('status') }}</div>
        @endif

        @if ($invite->review_remarks && $invite->status === \App\Models\PrejoiningInvite::STATUS_SENT)
            <div class="alert alert-warn"><strong>HR asked you to update:</strong> {{ $invite->review_remarks }}</div>
        @endif

        @if (! $editable)
            <div class="alert alert-ok">
                Your details were submitted on {{ $invite->submitted_at?->format('d M Y, h:i A') }}
                @if ($invite->status === \App\Models\PrejoiningInvite::STATUS_APPROVED) and have been reviewed by HR @endif.
                Contact HR if anything needs to change.
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                <strong>Please check the form:</strong>
                <ul style="margin:6px 0 0; padding-left:18px">
                    @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                </ul>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('prejoining.save', $token) }}" enctype="multipart/form-data">
        @csrf

        <div class="card" style="margin-top:20px">
            <h2>Personal details {!! $sectionPill('personal') !!}</h2>
            <div class="grid">
                <div><label class="req">First name</label><input type="text" name="first_name" value="{{ $v('first_name') }}" {{ $ro }}></div>
                <div><label>Middle name</label><input type="text" name="middle_name" value="{{ $v('middle_name') }}" {{ $ro }}></div>
                <div><label class="req">Last name</label><input type="text" name="last_name" value="{{ $v('last_name') }}" {{ $ro }}></div>
                <div><label class="req">Date of birth</label><input type="date" name="dob" value="{{ $v('dob') }}" {{ $ro }}></div>
                <div><label class="req">Gender</label>
                    <select name="gender" @disabled(! $editable)>
                        <option value="">—</option>
                        @foreach (\App\Services\EmployeeProfileService::GENDERS as $key => $label)
                            <option value="{{ $key }}" @selected($v('gender') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="req">Marital status</label>
                    <select name="marital_status" @disabled(! $editable)>
                        <option value="">—</option>
                        @foreach (\App\Services\EmployeeProfileService::MARITAL as $key => $label)
                            <option value="{{ $key }}" @selected($v('marital_status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="req">Blood group</label>
                    <select name="blood_group" @disabled(! $editable)>
                        <option value="">—</option>
                        @foreach (\App\Services\EmployeeProfileService::BLOOD_GROUPS as $group)
                            <option value="{{ $group }}" @selected($v('blood_group') === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Contact {!! $sectionPill('contact') !!}</h2>
            <div class="grid">
                <div><label class="req">Personal mobile</label><input type="tel" name="personal_mobile" value="{{ $v('personal_mobile') }}" placeholder="98XXXXXXXX" {{ $ro }}></div>
                <div><label>Alternate mobile</label><input type="tel" name="alternate_mobile" value="{{ $v('alternate_mobile') }}" {{ $ro }}></div>
                <div><label class="req">Personal email</label><input type="email" name="personal_email" value="{{ $v('personal_email') }}" {{ $ro }}></div>
            </div>
        </div>

        <div class="card">
            <h2>Address {!! $sectionPill('address') !!}</h2>
            @foreach (['current_address' => 'Current address', 'permanent_address' => 'Permanent address'] as $field => $title)
                <p class="muted" style="margin:14px 0 0"><strong>{{ $title }}</strong></p>
                <div class="grid" style="margin-top:6px">
                    <div class="full"><label class="req">Address line 1</label><input type="text" name="{{ $field }}[line1]" value="{{ $v("$field.line1") }}" {{ $ro }}></div>
                    <div class="full"><label>Address line 2</label><input type="text" name="{{ $field }}[line2]" value="{{ $v("$field.line2") }}" {{ $ro }}></div>
                    <div><label class="req">City</label><input type="text" name="{{ $field }}[city]" value="{{ $v("$field.city") }}" {{ $ro }}></div>
                    <div><label class="req">State</label><input type="text" name="{{ $field }}[state]" value="{{ $v("$field.state") }}" {{ $ro }}></div>
                    <div><label class="req">PIN code</label><input type="text" inputmode="numeric" maxlength="6" name="{{ $field }}[pincode]" value="{{ $v("$field.pincode") }}" {{ $ro }}></div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <h2>Emergency contact {!! $sectionPill('emergency') !!}</h2>
            <div class="grid">
                <div><label class="req">Name</label><input type="text" name="emergency_contact_name" value="{{ $v('emergency_contact_name') }}" {{ $ro }}></div>
                <div><label class="req">Relationship</label><input type="text" name="emergency_contact_relation" value="{{ $v('emergency_contact_relation') }}" placeholder="Father, Spouse…" {{ $ro }}></div>
                <div><label class="req">Phone</label><input type="tel" name="emergency_contact_phone" value="{{ $v('emergency_contact_phone') }}" {{ $ro }}></div>
            </div>
        </div>

        <div class="card">
            <h2>Statutory IDs {!! $sectionPill('statutory') !!}</h2>
            <div class="grid">
                <div><label class="req">PAN</label><input type="text" name="pan" value="{{ $v('pan') }}" placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase" {{ $ro }}></div>
                <div><label class="req">Aadhaar</label><input type="text" inputmode="numeric" name="aadhaar" value="{{ $v('aadhaar') }}" placeholder="12 digits" maxlength="14" {{ $ro }}></div>
                <div><label>UAN (if you have one)</label><input type="text" inputmode="numeric" name="uan" value="{{ $v('uan') }}" maxlength="12" {{ $ro }}></div>
            </div>
        </div>

        <div class="card">
            <h2>Salary bank account {!! $sectionPill('bank') !!}</h2>
            <div class="grid">
                <div><label class="req">Account holder name</label><input type="text" name="bank[account_holder_name]" value="{{ $v('bank.account_holder_name') }}" {{ $ro }}></div>
                <div><label class="req">Bank name</label><input type="text" name="bank[bank_name]" value="{{ $v('bank.bank_name') }}" {{ $ro }}></div>
                <div><label class="req">Account number</label><input type="text" inputmode="numeric" name="bank[account_number]" value="{{ $v('bank.account_number') }}" {{ $ro }}></div>
                <div><label class="req">IFSC</label><input type="text" name="bank[ifsc]" value="{{ $v('bank.ifsc') }}" placeholder="HDFC0001234" maxlength="11" style="text-transform:uppercase" {{ $ro }}></div>
                <div><label>Branch</label><input type="text" name="bank[branch_name]" value="{{ $v('bank.branch_name') }}" {{ $ro }}></div>
            </div>
        </div>

        <div class="card">
            <h2>Documents {!! $sectionPill('documents') !!}</h2>
            <p class="muted" style="margin:4px 0 8px">Photo (JPG, PNG) or PDF, up to 10 MB each.</p>
            @foreach (\App\Models\EmployeeDocument::types() as $type => $label)
                @php $uploaded = $docsByType->get($type, collect()); $required = array_key_exists($type, \App\Models\EmployeeDocument::REQUIRED_TYPES); @endphp
                <div class="doc">
                    <div>
                        <div @class(['req' => $required]) style="font-weight:600">{{ $label }}</div>
                        @forelse ($uploaded as $doc)
                            <div class="row muted" style="gap:8px">
                                <span>✓ {{ $doc->file_name }}</span>
                                @if ($doc->is_verified) <span class="pill pill-ok">Verified</span> @endif
                                @if ($editable && ! $doc->is_verified)
                                    <button type="submit" form="del-{{ $doc->id }}" class="btn-link btn-danger-link">Remove</button>
                                @endif
                            </div>
                        @empty
                            <div class="muted">{{ $required ? 'Not uploaded yet' : 'Optional' }}</div>
                        @endforelse
                    </div>
                    @if ($editable)
                        <input type="file" name="documents[{{ $type }}]" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    @endif
                </div>
            @endforeach
        </div>

        @if ($editable)
            <div class="sticky-actions">
                <button type="submit" name="action" value="save" class="btn btn-light">Save &amp; finish later</button>
                <button type="submit" name="action" value="submit" class="btn"
                        onclick="return confirm('Submit your details to HR? You will not be able to change them afterwards.')">Submit to HR</button>
            </div>
        @endif
    </form>

    @if ($editable)
        @foreach ($employee->documents as $doc)
            <form id="del-{{ $doc->id }}" method="POST" action="{{ route('prejoining.documents.delete', [$token, $doc]) }}" style="display:none">@csrf</form>
        @endforeach
    @endif
@endsection
