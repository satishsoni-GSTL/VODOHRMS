# VODOHRMS — Architecture & Database Design

Standalone Laravel HRMS for Head Office / Corporate employees. Independent Laravel 11 project (not a VODOCLM module). Admin panel built on **Filament v3**, RBAC via **spatie/laravel-permission**, bulk import/export via **maatwebsite/excel**, PDF payslips via **barryvdh/laravel-dompdf**.

## Implementation Status (2026-07-24, all phases + Reports + Audit Log complete)

- **Phase 1 – HR Core: complete.** Auth (login by employee code or email, lockout, login audit), Roles & Permissions, Organization Masters, Employee Master (with sensitive-field masking), Bulk Employee Upload, reporting-manager circular-hierarchy guard, role-based dashboards. 18 automated tests (`tests/Feature/Phase1SmokeTest.php`).
- **Phase 2 – Attendance & Leave: complete.** Shifts, Holiday Calendar, Attendance (manual + bulk import with conflict handling), Attendance Regularization, Leave Types/Policies, Leave Balances/Ledger, Leave Application, and a **generic reusable Approval Workflow Engine** (`workflow_definitions`/`workflow_levels`/`approval_instances`/`approval_actions` — see §3.7/§6) that both Attendance Regularization and Leave Application route through, and that Phase 3 (Expense) and Phase 6 (Loans, Resignation) will reuse via the `App\Contracts\Approvable` interface. Unified "Pending Approvals" inbox at `/admin/pending-approvals`. 5 automated tests (`tests/Feature/Phase2SmokeTest.php`).
- **Phase 3 – Expense & Approvals: complete.** Expense Categories, Expense Claims (multi-line via a form-only Repeater — not Filament's relationship-bound repeater, since claims are created through `ExpenseClaimService` rather than direct Eloquent writes), routed through the same Approval Workflow Engine as Phase 2 — proving out the engine's reuse goal. Seeded workflow demonstrates **conditional level-skipping**: Reporting Manager → Department Head (only if `amount > 10000`) → Finance. Finance-only "Record Payment" action creates an immutable `expense_payments` row (DB-unique on `expense_claim_id`, plus a service-level pre-check) so a claim can never be paid twice. 5 automated tests (`tests/Feature/Phase3SmokeTest.php`), including both the small-claim (manager→finance) and large-claim (manager→dept-head→finance) paths.
- **Phase 4 – Payroll: complete.** Salary Components (earnings HR-enters per employee; PF/ESIC/Professional Tax deductions and PF/ESIC employer contributions auto-computed as a configurable percentage-of-Basic or fixed amount), versioned Employee Salary Structures (revisions never overwrite — the prior row's `effective_to` is closed and a new row inserted, `increment_percent` computed automatically), Monthly Payroll Inputs (bonus/incentive/arrears/reimbursement/LOP-adjustment/loan/advance/TDS-adjustment), and the full Payroll Run lifecycle (`draft` → `calculated` → `finalized` → `locked`, with `reopened` unwinding the freeze) via `PayrollCalculationService`. Paid-days/LOP is computed day-by-day for the month from weekly-off + holiday calendar + attendance status + approved leave (respecting each `LeaveType.is_paid_leave` flag). Finalizing a run freezes that month's `attendances` rows (`is_frozen`); reopening un-freezes them. `payroll_run_employees` is DB-unique on `(payroll_run_id, employee_id)`, so double-generation for the same employee/month is structurally impossible; recalculation before finalization is supported via `updateOrCreate`. Payslip PDFs generated via `PayslipService` (barryvdh/laravel-dompdf) with an amount-in-words converter and YTD earnings/TDS, downloadable through a permission-gated controller route (`payslips.download`) rather than direct storage access — HR/Payroll can download any employee's, an employee only their own. ESS "My Salary Slips" page at `/admin/my-payslips`. Income tax (TDS) is **not** auto-calculated yet — Phase 5 owns that; today TDS only enters payroll as a manual `tds_adjustment` payroll input, per the architecture's own phasing. 6 automated tests (`tests/Feature/Phase4SmokeTest.php`), covering auto-computed statutory components, structure versioning, LOP calculation, the full finalize→lock→reopen→recalculate lifecycle, and payslip generation.
- **Phase 5 – Income Tax: complete.** Financial Years with configurable, non-hard-coded Old/New regime tax slabs, standard deduction, Sec 87A-style rebate, JSON-configurable surcharge brackets, and cess — `IncomeTaxCalculationService` computes slab tax generically off whatever rows exist for a financial year, so slab changes (e.g. a new budget) never require a code change. Tax Sections (80C/80D/HRA/Home Loan Interest/NPS, configurable, not hard-coded) with employee declarations that HR verifies/rejects (`TaxDeclarationService`) — approved+capped amounts feed old-regime exemptions. `compareRegimes()` computes and stores both old and new regime projections side by side and recommends the lower-tax one, without forcing the choice — the employee makes the final call via `EmployeeTaxRegime` (an append-only history, same pattern as salary structures). Monthly TDS is now **fully automatic and integrated into payroll**: `PayrollCalculationService::calculateForEmployee()` calls `IncomeTaxCalculationService::monthlyTdsForPayroll()` after persisting the month's gross earnings (ordering matters — the projection sums YTD gross from the database, so it must run after gross is saved, not before) and adds an `"Income Tax (TDS)"` deduction line automatically; the manual `tds_adjustment` payroll input still exists for one-off corrections on top of the automatic figure. Projected annual income = salary already paid (from `payroll_run_employees` within the financial year) + remaining months projected at the current salary structure's monthly gross. Seeded FY 2026-27 with realistic old/new regime slabs. 6 automated tests (`tests/Feature/Phase5SmokeTest.php`), including a hand-verified slab-tax arithmetic check, rebate-zeroes-tax-at-threshold, regime comparison, declaration-reduces-taxable-income, and payroll-auto-deducts-TDS end-to-end.
- **Phase 6 – Employee Lifecycle: complete.** Onboarding checklist (`OnboardingService::refresh()` recomputes all 8 boolean flags — documents, statutory PAN/UAN, bank details, department/designation/manager, current salary structure, user login, asset allocation — from live related data rather than trusting manually-ticked boxes, then derives `completion_percent`) plus Employee Assets tracking. Loans & Advances (`EmployeeLoan`/`LoanService`) and Resignation/Exit (`Resignation`/`ExitClearance`/`FullFinalSettlement`) both implement `Approvable` and route through the **same** Approval Workflow Engine from Phase 2/3 — Loan: Employee → Reporting Manager → HR → Finance; Resignation: Employee → Reporting Manager → HR (seeded in `Phase6Seeder`) — proving the engine's reuse goal a third and fourth time. `LoanService::recoverForPayroll()` is idempotent per `payroll_run_employee_id` (reverses-then-recomputes on recalculation, mirroring the TDS/salary-line pattern from Phase 4/5) and is wired into `PayrollCalculationService::calculateForEmployee()` as a `"Loan Recovery"`/`"Salary Advance Recovery"` deduction line, capped at `outstanding_balance`, auto-closing the loan when it hits zero. Resignation submission auto-creates 5 `ExitClearance` rows (manager/it/admin/finance/hr departments); `ExitClearanceService::clear()`/`reject()` are the only mutators. `FnFSettlementService::calculate()` auto-computes leave encashment (from encashment-eligible `EmployeeLeaveBalance` × current daily rate), loan/advance recovery (from active `EmployeeLoan.outstanding_balance`), reimbursement (approved `ExpenseClaim` totals), and notice-period shortfall recovery, leaving pending salary/bonus/other-earnings/TDS/other-deductions as HR-editable manual lines; `approve()` → `markPaid()` sets `Employee.status = exited`. New permissions: `loan.view`/`loan.manage`, `onboarding.manage`, `resignation.view`/`resignation.manage`, `exit.manage`, `fnf.process` — `OnboardingMasterPolicy` gates `OnboardingChecklist`/`EmployeeAsset` (HR-tier only, matching the doc's `[HR]`-only nav group), while the four Approvable resources use the established inline-`can()` + `ScopesToOwnTeam` pattern (no policy class) like every other per-employee workflow resource in this codebase. 5 automated tests (`tests/Feature/Phase6SmokeTest.php`): onboarding auto-computation, full loan approval chain with payroll recovery (including a non-doubling recalculation check), and the full resignation → exit clearance → F&F calculate → approve → pay → employee-exited lifecycle.
- **Reports & Audit Log: complete.** Generic before/after audit trail via a reusable `App\Models\Concerns\Auditable` trait (`bootAuditable()` hooks `created`/`updated`/`deleted` model events, configurable per model via `auditedEvents()`/`auditModule()` overrides) applied to: `Employee` (full CRUD), `EmployeeBankDetail`/`EmployeeStatutoryDetail` (create/update — the `'encrypted'` cast means `getAttributes()` naturally logs ciphertext, not plaintext PAN/Aadhaar/account numbers), `EmployeeSalaryStructure` (create/update, since revisions close the prior row), and all five `Approvable` workflow-request models (`created` only — their status transitions are logged separately). `AuditLogService::log()` is the single write path, called from the `Auditable` trait AND centrally from `ApprovalWorkflowService::act()` (logs every `approve`/`reject`/`send_back` across Leave/Attendance-Regularization/Expense/Loan/Resignation — one hook covers all five modules, the same reuse pattern as the workflow engine itself) and from `PayrollCalculationService::finalize()/lock()/reopen()`. Read-only `AuditLogResource` at `/admin/audit-logs`, gated by `audit.view` (Super Admin only, per the RBAC matrix) via a new `AuditLogPolicy` — since Filament's `ListRecords` page has an empty `authorizeAccess()` hook by default (unlike `ViewRecord`/`EditRecord`, which already gate via `canView()`/`canEdit()`), `ListAuditLogs` explicitly overrides it with `abort_unless(static::getResource()::canViewAny(), 403)` to actually enforce the policy rather than just hiding the nav link. Reports module: a single `Reports` Filament page (`/admin/reports`) with a month picker and permission-gated report cards (Employee Master, Attendance, Leave, Expense, Payroll, Loans & Advances), each downloading an `.xlsx` via `maatwebsite/excel` through a dedicated `ReportDownloadController` (`/reports/{type}/download`) rather than a Livewire action, since file downloads don't survive Livewire's AJAX request cycle reliably. Team-scoped reports (Attendance/Leave/Expense/Loan) reuse `ScopesToOwnTeam` exactly like the underlying resources — a Manager with no explicit permissions still gets their team's data (detected via `Employee::directReports()->exists()`), while the Employee Master and Payroll reports are HR/Payroll/Finance-tier only with no manager fallback, matching the doc's RBAC matrix (`Reports | Export all | Export HR | View | Export payroll | Export finance | Export team | -`). 5 automated tests (`tests/Feature/AuditAndReportsSmokeTest.php`): audit-log page access control, an Employee update writing an audit row, a full leave submit→approve cycle writing both a `create` and an `approve` audit row, and report-download permission gating across HR/manager/plain-employee.
- **Known simplification (Reports/Audit):** Only a curated set of "sensitive" models are wired into `Auditable` (Employee master data, bank/statutory details, salary structures, and workflow-request creation) rather than every model in the system — this matches spec intent ("audit trail for sensitive changes") without instrumenting low-value tables like lookup masters. Extend by adding `use Auditable;` plus an `auditModule()` override to any model that later needs it.
- **Known simplification to revisit:** `ApprovalWorkflowService::applicableLevels()` re-evaluates `WorkflowLevel::condition_rules` against the request's *current* state each time a level is resolved, rather than snapshotting the applicable level list at submission time. Confirmed correct in Phase 3 testing as long as the condition fields (grade, department, amount) don't change mid-approval — still worth hardening if a future module needs amounts editable while pending.
- **Known simplification (Phase 6):** the `ExitClearanceResource`'s "manager" department clearance is completed by HR today rather than routed to the actual reporting manager — the architecture doc's nav map only lists Exit & F&F under `[HR, Finance]`, so managers have no dedicated screen for it; worth a dedicated manager-scoped clearance view if this becomes a real bottleneck. Also, `EmployeeLoan.applyApprovalOutcome()` activates a loan with whatever `monthly_recovery` HR has set by the time Finance gives final sign-off — if HR forgets to fill it in before Finance approves, the loan goes active with `monthly_recovery = null` and silently never recovers anything in payroll; worth a validation guard if this trips up real usage.
- **Gotcha for Filament repeaters:** a `Select::make(...)->relationship(...)` nested inside a `Repeater` tries to resolve the relation against the repeater's *parent* model, not the row being built — if the parent has no such relation (e.g. `ExpenseClaim` has no `category()` relation, only `ExpenseClaimLine` does), it crashes with "prepareQueryForNoConstraints(): ... null given". Use plain `->options(...)` instead of `->relationship(...)` for any Select inside a Repeater whose items aren't Eloquent-relationship-bound.
- **Gotcha for future model work:** always cast date-only columns as `'date:Y-m-d'`, never plain `'date'` — the plain cast serializes with a full `Y-m-d H:i:s` timestamp on save, which breaks exact-string date lookups (masked on MySQL since it truncates leniently, but fails hard on SQLite/tests). Every date-only column in this codebase was fixed to `'date:Y-m-d'` on 2026-07-24.
- **Gotcha for feature tests that switch users mid-test:** calling `$this->actingAs($userA)`, hitting an `/admin/*` (Filament panel) route, then calling `$this->actingAs($userB)` and hitting another `/admin/*` route **within the same test method** silently logs the second user out and 302-redirects to `/admin/login` — the panel's `AuthenticateSession` middleware compares the session's stored `password_hash_web` (set on the first login) against the newly-acting user's actual password hash, finds a mismatch, and force-logs-out. `actingAs()` doesn't refresh that session key the way a real login does. Routes without `AuthenticateSession` in their middleware stack (e.g. the plain `->middleware('auth')` routes in `routes/web.php`) are unaffected. Fix: split into separate test methods per acting user rather than switching mid-test when the route goes through the Filament panel.
- **Gotcha for disposable tinker-style route checks:** `auth()->login($user)` outside an HTTP request context throws (`SessionGuard::setRequest()` needs a bound request), and simply calling `$request->setLaravelSession($session)` before `$kernel->handle()` is not enough either — `StartSession` middleware re-resolves its own session store from the manager rather than trusting what's already on the request, discarding a manually-attached session. The fix: after `$kernel->bootstrap()`, force `config(['session.driver' => 'array'])` and pre-seed `app('session')->driver()` (the manager caches driver instances, so this is the *same* object `StartSession` fetches on every subsequent `$kernel->handle($request)` call) with the `login_web_<sha1 of SessionGuard::class>` key set to the user's ID — then every request in the loop is already "logged in" via session, exactly like a real browser.

---

## 1. Module List

| # | Module | Purpose |
|---|--------|---------|
| 1 | Authentication | Login (code/email + password), forgot/reset/change password, lockout |
| 2 | Users | Auth accounts, mapped 1:1 to Employees |
| 3 | Roles & Permissions | Configurable roles, module-wise permissions (spatie) |
| 4 | Organization | Company, Branch, Location, Department, Sub-Department, Designation, Grade, Cost Center, Employee/Employment Type |
| 5 | Employees | Employee master (personal, address, employment, statutory, bank) |
| 6 | Employee Import | Bulk create/update via Excel with preview + error report |
| 7 | Documents | Employee document uploads (ID proofs, offer letter, etc.) |
| 8 | Attendance | Daily punches, computed hours, manual/bulk import |
| 9 | Shifts | Shift masters + employee shift assignment |
| 10 | Holidays | Holiday calendar by company/branch/location/state |
| 11 | Leave | Leave types, policy, balances, ledger, application |
| 12 | Approval Workflow | Generic reusable multi-level approval engine |
| 13 | Expenses | Expense categories, claims, claim lines, approval, payment |
| 14 | Payroll | Salary components, structures, monthly payroll run, payslips |
| 15 | Income Tax | FY tax slabs (old/new regime), regime selection, declarations, TDS |
| 16 | Loans & Advances | Employee loans/advances with EMI recovery via payroll |
| 17 | Notifications | In-app notification center |
| 18 | Onboarding | Onboarding checklist + completion % |
| 19 | Exit / F&F | Resignation, exit clearance, full & final settlement |
| 20 | Reports | Cross-module exportable reports |
| 21 | Audit Logs | Generic before/after audit trail for sensitive changes |

## 2. Menu Structure

```
Dashboard (role-specific)

Organization          [HR Admin+]
  Companies / Branches / Locations / Departments / Sub-Departments
  Designations / Grades / Cost Centers / Employee Types / Employment Types

Employees              [HR Admin, HR Executive]
  Employee List / Add Employee / Bulk Import / Import History
  Documents / Onboarding

Attendance
  Daily Attendance / Bulk Import / Shifts / Holiday Calendar
  My Attendance (ESS) / Regularization Requests

Leave
  Leave Types & Policy / Leave Balances / Leave Ledger
  My Leave / Apply Leave (ESS)

Approvals               [Manager, HR, Finance]
  Pending Approvals (unified queue)

Expenses
  Expense Categories / All Claims
  My Expenses / Submit Claim (ESS)

Payroll                 [Payroll Admin, Finance]
  Salary Components / Salary Structures / Employee Salary Assignment
  Bulk Salary Upload / Monthly Payroll Inputs / Run Payroll / Payroll Register
  My Salary Slips (ESS)

Income Tax
  Financial Years / Tax Slabs (Old/New) / Employee Regime
  Investment Declarations / Proof Verification / Tax Comparison / Monthly TDS

Loans & Advances
  All Loans / My Loans (ESS)

Onboarding              [HR]
Exit & F&F               [HR, Finance]
  Resignations / Exit Clearance / F&F Settlement

Reports
Settings
  Roles & Permissions / Users / Audit Logs / Notification Templates
```

Employee (ESS) login sees only: Dashboard, My Profile, My Attendance, My Leave, My Expenses, My Salary, My Tax, My Loans, My Requests.
Manager login additionally sees: **My Team**, **Approvals**.

## 3. Database Schema

Conventions: every table has `id, created_at, updated_at`; soft-deletable master/transaction tables also get `deleted_at`. FKs shown as `-> table.column`. History-preserving tables never overwrite prior rows — they insert new versioned/dated rows instead.

### 3.1 Auth & RBAC
- **users**: id, employee_id -> employees.id (nullable, unique), name, email (unique), employee_code (unique, nullable, login alias), password, must_change_password (bool), is_active (bool), last_login_at, last_login_ip, failed_login_attempts, locked_until, remember_token
- **password_reset_tokens**: email, token, created_at
- Spatie tables: **roles**, **permissions**, **model_has_roles**, **model_has_permissions**, **role_has_permissions** (guard-based; permission name convention `module.action`, e.g. `employee.view`, `payroll.finalize`)
- **login_audits**: id, user_id, ip_address, user_agent, status (success/failed), reason, created_at

### 3.2 Organization
- **companies**: id, name, code (unique), address, gstin, pan, logo_path, is_active
- **branches**: id, company_id -> companies, name, code, address, is_active
- **locations**: id, branch_id -> branches, name, city, state, country, pincode, is_active
- **departments**: id, company_id -> companies, name, code, is_active
- **sub_departments**: id, department_id -> departments, name, code, is_active
- **designations**: id, name, code, department_id -> departments (nullable), is_active
- **grades**: id, name, code, level (int, for ordering/approval rules), is_active
- **cost_centers**: id, name, code, company_id -> companies, is_active
- **employee_types**: id, name (Head Office, Field, etc.), is_active
- **employment_types**: id, name (Permanent, Contract, Probation, Intern), is_active

### 3.3 Employees
- **employees**: id, employee_code (unique), first_name, middle_name, last_name, display_name, dob, gender, marital_status, blood_group, personal_mobile, alternate_mobile, personal_email, official_email (unique), profile_photo_path, current_address(json), permanent_address(json), city, state, country, pincode, company_id, branch_id, location_id, department_id, sub_department_id, designation_id, grade_id, cost_center_id, employee_type_id, employment_type_id, reporting_manager_id -> employees.id (nullable), hr_manager_id -> employees.id (nullable), date_of_joining, confirmation_date, probation_period_days, notice_period_days, default_shift_id -> shifts.id, weekly_off (json, days array), status (enum: active, probation, notice_period, resigned, terminated, exited, inactive), created_by, updated_by
- **employee_statutory_details**: id, employee_id (unique) -> employees, pan, aadhaar (encrypted), uan, pf_number, esic_number, professional_tax_applicable, tax_regime_default
- **employee_bank_details**: id, employee_id -> employees, account_holder_name, bank_name, account_number (encrypted), ifsc, branch_name, is_primary, effective_from
- **employee_documents**: id, employee_id -> employees, document_type, file_path, file_name, uploaded_by, uploaded_at, is_verified
- **employee_import_batches**: id, file_name, file_path, uploaded_by, uploaded_at, total_rows, success_rows, failed_rows, status (validating/previewed/imported/failed)
- **employee_import_rows**: id, batch_id -> employee_import_batches, row_number, raw_data(json), status (valid/invalid/imported), errors(json), employee_id -> employees (nullable, set after import)

### 3.4 Reporting Hierarchy
Modeled via `employees.reporting_manager_id` self-reference. Circular-reference check enforced in service layer (walk-up chain before save; reject if new manager's own chain already contains the employee).

### 3.5 Attendance & Shifts
- **shifts**: id, name, type (general/flexible/rotational/custom), start_time, end_time, grace_minutes, break_minutes, min_full_day_hours, min_half_day_hours, late_mark_after_minutes, early_going_before_minutes, is_active
- **employee_shifts**: id, employee_id -> employees, shift_id -> shifts, effective_from, effective_to (nullable) — history-preserving, never overwritten
- **holidays**: id, name, date, type (national/state/company/optional), company_id (nullable), branch_id (nullable), location_id (nullable), state (nullable)
- **attendances**: id, employee_id -> employees, attendance_date, first_in, last_out, total_hours, effective_hours, late_minutes, early_going_minutes, overtime_minutes, status (present/absent/half_day/wfh/on_duty/weekly_off/holiday/leave/missing_punch), source (biometric/manual/import/self), remarks. Unique (employee_id, attendance_date).
- **attendance_punches**: id, attendance_id -> attendances, punch_time, punch_type (in/out), source
- **attendance_import_batches** / **attendance_import_rows**: same pattern as employee import; conflicts (existing row for same employee+date) flagged, not silently overwritten — `conflict_action` (skip/overwrite/merge) chosen by approver at confirm step
- **attendance_regularizations**: id, employee_id -> employees, attendance_date, request_type (missing_punch/wrong_in/wrong_out/wfh/on_duty/client_visit/other), old_values(json), requested_values(json), reason, attachment_path, status (pending/manager_approved/hr_approved/approved/rejected/sent_back), workflow_instance_id -> approval_instances

### 3.6 Leave
- **leave_types**: id, name, code, annual_entitlement, accrual_frequency (monthly/annual/none), carry_forward_allowed, max_carry_forward, encashment_allowed, allow_negative_balance, half_day_allowed, sandwich_rule_applicable, probation_allowed, min_days_per_request, max_days_per_request, attachment_required, is_active
- **leave_policies**: id, leave_type_id -> leave_types, applicable_to (json: employee_type/grade/department scoping), is_active — configurable policy variant per leave type
- **employee_leave_balances**: id, employee_id -> employees, leave_type_id -> leave_types, year, opening_balance, credited, adjusted, used, lapsed, encashed, closing_balance (computed). Unique (employee_id, leave_type_id, year)
- **leave_ledger_entries**: id, employee_id -> employees, leave_type_id -> leave_types, entry_date, type (opening/credit/debit/adjustment/lapse/encashment), days, balance_after, reference_type, reference_id, remarks — append-only audit trail feeding balances
- **leave_applications**: id, employee_id -> employees, leave_type_id -> leave_types, from_date, to_date, days, is_half_day, half_day_session, reason, attachment_path, status (draft/pending/approved/rejected/sent_back/cancelled), workflow_instance_id -> approval_instances

### 3.7 Approval Workflow Engine (generic, reusable)
- **workflow_definitions**: id, module (leave/attendance_regularization/expense/loan/salary_advance/resignation/other), name, is_active
- **workflow_levels**: id, workflow_definition_id -> workflow_definitions, sequence, approver_type (reporting_manager/department_head/hr/finance/management/specific_role/specific_user), approver_role_id (nullable), approver_user_id (nullable), condition_rules(json: e.g. amount>50000, grade>=X, department=Y) — lets different requests skip/add levels
- **approval_instances**: id, workflow_definition_id -> workflow_definitions, requestable_type, requestable_id (polymorphic — the Leave/Expense/etc. record), current_level, status (pending/approved/rejected/sent_back/cancelled)
- **approval_actions**: id, approval_instance_id -> approval_instances, level, approver_id -> users, action (approve/reject/send_back), remarks, acted_at — full approval history, immutable

### 3.8 Expenses
- **expense_categories**: id, name, code, requires_bill, requires_project, gl_code, is_active
- **expense_claims**: id, claim_number (unique), employee_id -> employees, claim_date, project_client, status (draft/submitted/manager_approved/pending_finance/approved/partially_approved/rejected/paid), workflow_instance_id -> approval_instances, total_requested_amount, total_approved_amount
- **expense_claim_lines**: id, expense_claim_id -> expense_claims, category_id -> expense_categories, expense_date, amount, requested_amount, approved_amount, description, vendor, bill_number, payment_mode, receipt_path
- **expense_payments**: id, expense_claim_id -> expense_claims, paid_amount, paid_on, payment_reference, paid_by — prevents double payment via unique constraint / status guard

### 3.9 Payroll & Salary
- **salary_components**: id, name, code, type (earning/deduction/employer_contribution), calculation_type (fixed/percentage/formula), percentage_of (nullable, references another component code), formula (nullable expression), is_taxable, is_pf_applicable, is_esic_applicable, is_prorated, is_ctc_component, is_gross_component, show_on_payslip, sequence
- **employee_salary_structures**: id, employee_id -> employees, effective_from, effective_to (nullable, set when superseded — never deleted), annual_ctc, monthly_gross, previous_ctc, revised_ctc, increment_percent, approved_by, remarks, is_active — a new row per revision, history preserved
- **employee_salary_structure_lines**: id, employee_salary_structure_id -> employee_salary_structures, salary_component_id -> salary_components, monthly_amount, annual_amount
- **payroll_runs**: id, payroll_month (YYYY-MM), company_id, status (draft/calculated/reviewed/finalized/locked/reopened), locked_at, locked_by, reopened_reason. Unique (payroll_month, company_id)
- **payroll_run_employees**: id, payroll_run_id -> payroll_runs, employee_id -> employees, salary_structure_id -> employee_salary_structures, paid_days, lop_days, gross_earnings, total_deductions, net_pay, status (calculated/reviewed/finalized). Unique (payroll_run_id, employee_id) — **prevents double payroll generation for same employee/month**
- **payroll_run_employee_lines**: id, payroll_run_employee_id -> payroll_run_employees, salary_component_id -> salary_components, amount, component_type
- **payroll_inputs**: id, employee_id -> employees, payroll_month, type (bonus/incentive/arrears/additional_earning/additional_deduction/reimbursement/lop_adjustment/tds_adjustment), amount, reason, created_by, approved_by — every manual adjustment audited
- **payslips**: id, payroll_run_employee_id -> payroll_run_employees (unique), employee_id -> employees, payroll_month, pdf_path, generated_at — immutable snapshot; regenerated only by reopening the payroll run (new record superseding old, old retained)

### 3.10 Income Tax
- **financial_years**: id, name (e.g. 2026-27), start_date, end_date, assessment_year, is_active
- **tax_regime_slabs**: id, financial_year_id -> financial_years, regime (old/new), income_from, income_to, tax_percent, sequence
- **tax_regime_configs**: id, financial_year_id -> financial_years, regime, standard_deduction, rebate_limit_income, rebate_max_amount, surcharge_rules(json), cess_percent, regime_change_allowed (bool)
- **tax_sections**: id, code (80C/80D/HRA/...), name, max_limit, financial_year_id -> financial_years, is_active — configurable, not hardcoded
- **employee_tax_regimes**: id, employee_id -> employees, financial_year_id -> financial_years, selected_regime, selection_date, lock_date, changed_by. History row per change (no overwrite)
- **employee_tax_declarations**: id, employee_id -> employees, financial_year_id -> financial_years, tax_section_id -> tax_sections, declared_amount, proof_path, approved_amount, rejected_amount, eligible_amount, hr_remarks, status (declared/proof_submitted/verified/rejected)
- **employee_tax_projections**: id, employee_id -> employees, financial_year_id -> financial_years, regime, projected_annual_income, total_exemptions, taxable_income, tax_before_rebate, rebate, surcharge, cess, final_tax, tds_deducted_till_date, remaining_tax, projected_monthly_tds, calculated_at — recalculated snapshot each payroll cycle / declaration change

### 3.11 Loans & Advances
- **employee_loans**: id, employee_id -> employees, type (loan/salary_advance), requested_amount, reason, request_date, approved_amount, installments, monthly_recovery, recovery_start_month, outstanding_balance, status (pending/manager_approved/hr_approved/finance_approved/active/closed/rejected), workflow_instance_id -> approval_instances
- **employee_loan_recoveries**: id, employee_loan_id -> employee_loans, payroll_run_employee_id -> payroll_run_employees, recovered_amount, recovery_month — recovery can never push outstanding_balance below 0 (enforced in service layer)

### 3.12 Notifications
- **notifications** (Laravel default polymorphic notifications table): id (uuid), type, notifiable_type, notifiable_id, data(json), read_at

### 3.13 Onboarding
- **onboarding_checklists**: id, employee_id (unique) -> employees, personal_details_done, documents_done, statutory_done, bank_done, department_done, salary_done, login_done, asset_allocation_done, completion_percent (computed)
- **employee_assets**: id, employee_id -> employees, asset_type, asset_tag, allocated_on, returned_on

### 3.14 Exit & F&F
- **resignations**: id, employee_id -> employees, resignation_date, reason, requested_last_working_date, notice_period_days, manager_comments, hr_comments, approved_last_working_date, status (pending/manager_approved/hr_approved/rejected/withdrawn), workflow_instance_id -> approval_instances
- **exit_clearances**: id, resignation_id -> resignations, department (manager/it/admin/finance/hr), status (pending/cleared/rejected), remarks, cleared_by, cleared_at
- **full_final_settlements**: id, resignation_id -> resignations (unique), employee_id -> employees, pending_salary, bonus_incentive, reimbursement, leave_encashment, other_earnings, notice_recovery, loan_recovery, advance_recovery, tds, other_deductions, final_amount (computed), status (draft/calculated/approved/paid), calculated_by, approved_by, paid_at

### 3.15 Audit
- **audit_logs**: id, action (create/update/delete/approve/reject/reopen), module, auditable_type, auditable_id, old_values(json), new_values(json), user_id -> users, ip_address, reason, created_at — written for every sensitive change listed in spec §41 via model observers / service-layer hooks, never via manual controller calls only

### 3.16 Import Framework (shared)
All bulk uploads (`employee`, `attendance`, `leave_opening_balance`, `salary_structure`, `payroll_input`) reuse one polymorphic pattern: `import_batches` (importable_type, file info, counts, status) + `import_rows` (batch_id, row_number, raw_data, status, errors, imported_id). One shared `ImportBatch`/`ImportRow` pair with a `type` discriminator, rather than N duplicated table pairs — keeps the "download template → upload → validate → preview → confirm → summary" flow identical everywhere.

*(Note: §3.3 shows employee_import_batches/rows as a concrete example; final migration will implement this as the shared `import_batches`/`import_rows` pair with `importable_type` discriminator to avoid duplicating the pattern five times.)*

## 4. Role & Permission Matrix (initial seed)

| Module | Super Admin | HR Admin | HR Executive | Payroll Admin | Finance | Manager | Employee |
|---|---|---|---|---|---|---|---|
| Organization Masters | CRUD | CRUD | View | View | View | - | - |
| Employee Master | CRUD | CRUD | Add/Edit/View | View | View (non-sensitive) | View (team, non-sensitive) | View (self) |
| Bank/Statutory/Salary (sensitive) | View/Edit | View/Edit | - | View/Edit | View | - | View (self) |
| Bulk Import | Import | Import | Import (employee only) | Import (salary) | - | - | - |
| Attendance | CRUD | CRUD | CRUD | View | View | View (team), Approve regularization | View/Apply (self) |
| Leave | CRUD, configure policy | CRUD, configure policy | Process | View | View | Approve (team) | Apply (self) |
| Expense | Approve/Process | Approve | Process | Process | Approve/Process/Finalize | Approve (team) | Submit (self) |
| Payroll | Process/Finalize/Reopen | View | - | Process/Finalize/Reopen | Approve/Finalize | - | View own payslip |
| Income Tax | Configure | Configure, Verify | Verify | View | View | - | Declare (self) |
| Loans | Approve | Approve | Process | Process | Approve | Approve (team) | Request (self) |
| Reports | Export all | Export HR | View | Export payroll | Export finance | Export team | - |
| Roles/Users/Audit | CRUD | - | - | - | - | - | - |

Permissions are stored as granular `module.action` rows (view/add/edit/delete/import/export/approve/reject/process/finalize/reopen) assigned to roles via spatie/laravel-permission — the table above is the seed default, fully editable at runtime through Settings → Roles & Permissions.

## 5. Employee Bulk-Upload Template (columns, in order)

```
employee_code*, first_name*, middle_name, last_name*, official_email*, personal_mobile*,
dob*, date_of_joining*, company_code*, branch_code*, location_code*, department_code*,
designation_code*, grade_code, employee_type_code*, employment_type_code*,
reporting_manager_employee_code, shift_code, weekly_off, pan, uan, pf_number, esic_number,
bank_name, account_number, ifsc, basic_salary, ctc
```
`*` = required. Reference columns (company/branch/department codes etc.) are validated against master tables; salary/bank columns only accepted from users holding the `payroll.import` / sensitive-data permission — otherwise rejected with a row error rather than silently ignored.

Flow: Download Template → Upload → Validate (per-row, non-blocking — valid rows still import even if others fail) → Preview → Error Report (downloadable) → Confirm Import → Import Summary (total/success/failed) — implemented once via the shared Import Framework (§3.16) and reused for attendance, salary structure, leave opening balance, and payroll inputs.

## 6. Manager Approval Workflow (generic engine, §3.7)

```
Request created (Leave / Attendance Regularization / Expense / Loan / Resignation / Other)
   -> approval_instances row created against workflow_definitions for that module
   -> workflow_levels evaluated in sequence; condition_rules (amount, grade, department)
      decide which levels apply for this specific request
   -> at each level: Approve / Reject / Send Back (remarks required for reject/send-back)
   -> Send Back returns to requester for edit + resubmit at level 1
   -> Reject terminates the request
   -> Approve at final level marks approval_instances.status = approved and the
      requestable record (Leave/Expense/...) transitions to its own "approved" state
   -> approval_actions logs every action permanently (full audit trail)
```
Default level chains (editable per module in Settings):
- Leave: Employee → Reporting Manager → (HR, if policy requires)
- Attendance Regularization: Employee → Reporting Manager → (HR, if configured)
- Expense: Employee → Reporting Manager → HOD (if amount > threshold) → Finance
- Loan/Advance: Employee → Reporting Manager → HR → Finance
- Resignation: Employee → Reporting Manager → HR

## 7. Payroll Workflow

```
Select Payroll Month + Company
 -> Load Active Employees (status active/probation/notice_period as of month)
 -> Freeze Attendance for the month (attendance edits blocked once frozen)
 -> Calculate Paid Days / LOP (attendance + approved leave + holidays + weekly off)
 -> Load latest Employee Salary Structure effective for the month
 -> Load approved Payroll Inputs (bonus, incentive, arrears, reimbursement, adjustments)
 -> Load active Loan/Advance recoveries (respecting outstanding_balance)
 -> Calculate Statutory Deductions (PF, ESIC, Professional Tax)
 -> Calculate Income Tax / TDS (from employee_tax_projections, recalculated this cycle)
 -> Calculate Gross, Total Deductions, Net Pay -> payroll_run_employees + lines
 -> Review (payroll_admin/finance can inspect, edit inputs, recalculate — draft/calculated state)
 -> Finalize (status -> finalized; payroll_run_employees become immutable)
 -> Lock (status -> locked)
 -> Generate Payslips (PDF, immutable snapshot)
 -> Reopen (permission-gated + reason required + audit_logs entry) re-enables edits for
    that run only; historical payslips already issued are NOT altered — a superseding
    payslip is generated and both remain traceable
```
Guard: unique (payroll_run_id, employee_id) on `payroll_run_employees` makes duplicate generation for the same employee/month impossible at the DB level.

## 8. Income Tax / TDS Workflow

```
Financial Year + regime slabs/config configured once (immutable once payroll runs reference it)
 -> Employee selects Old or New regime (employee_tax_regimes; locked after lock_date unless
    HR-configured change window is open)
 -> [Old regime] Employee declares investments (80C/80D/HRA/home loan/NPS/...) with proofs
    -> HR/Payroll verifies -> approved_amount vs rejected_amount recorded
 -> System computes Old vs New comparison side by side (both always computed regardless of
    selection, so the employee can compare)
 -> Monthly payroll cycle recalculates employee_tax_projections:
      projected annual income (salary paid + expected remaining + bonus/incentive +
      previous employer income + other income) - exemptions/deductions (per selected
      regime) = taxable income -> tax slabs (financial_year_id + regime specific) ->
      rebate -> surcharge -> cess = final tax
      remaining tax = final tax - TDS already deducted
      projected monthly TDS = remaining tax / remaining payroll months
 -> Recalculated whenever: declaration approved/changed, regime changed (if allowed),
    salary revised, or a new payroll month runs
```

## 9. Recommended Implementation Sequence

Matches spec §47 exactly:

1. **Phase 1 – HR Core**: Auth → Roles & Permissions → Organization Masters → Employee Master → Bulk Employee Upload → Employee Login → Manager Hierarchy → Admin Dashboard
2. **Phase 2 – Attendance & Leave**: Shift → Holiday → Attendance → Bulk Attendance → Regularization → Leave Policy → Leave Balance → Leave Application → Manager Approval
3. **Phase 3 – Expense & Approvals**: Expense Master → Expense Claim → Manager Approval → Finance Approval → Settlement → Common Approval Dashboard
4. **Phase 4 – Payroll**: Salary Components → Salary Structure → Employee Salary Assignment → Bulk Salary Upload → Attendance Integration → Monthly Payroll Inputs → Payroll Calculation → Review → Finalization → Salary Slip
5. **Phase 5 – Income Tax**: Financial Year → Old/New Regime Config → Tax Slabs → Employee Regime Selection → Investment Declaration → Proof Verification → Comparison → Annual Projection → Monthly TDS → Payroll Integration
6. **Phase 6 – Employee Lifecycle**: Onboarding → Loans & Advances → Resignation → Exit Clearance → F&F Settlement → Reports → Audit

This document is the reference for all phases; update it if a schema decision changes during implementation rather than letting the doc drift from the code.
