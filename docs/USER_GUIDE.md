# VODOHRMS — User Guide (How to Use, Flows & Menu)

This is the practical, day-to-day companion to `ARCHITECTURE.md` (which covers database schema and technical design). This guide covers **how to log in, what the menu actually looks like today, and how to complete each common task end-to-end**, written straight from the built application (not the original plan — a few things were simplified during implementation; where that happened, it's called out).

---

## 1. Getting Started

**URL:** `/admin` (Filament admin panel — this is the only login surface; there is no separate "employee portal" app).

**Logging in:**
- Enter your **Employee Code or Email** (either works) + password.
- 5 failed attempts locks the account for 15 minutes. Every attempt (success, failure, locked, inactive) is written to the login audit trail.
- If your account was created by HR (new hire) or migrated from another system, `must_change_password` is set — you'll be forced to set a new password on first login.
- An inactive account (`is_active = false`) cannot log in at all — contact HR.

**After login:** you land on the **Dashboard**, which shows different widgets depending on your role:
- Everyone: `AccountWidget` (your profile snapshot).
- HR/Admin-tier roles: `EmployeeStatsOverview` (headcount, active/probation/notice-period counts, etc.).
- Managers: `MyTeamOverview` (your direct + indirect reports at a glance).

There is **one login panel for everyone** — Super Admin, HR, Payroll, Finance, Managers, and plain Employees all use the same `/admin` panel. What differs is which menu items and rows they can see, driven entirely by permissions and row-level scoping (see §3).

---

## 2. Roles — what each one is actually for

Seeded by `RolesAndPermissionsSeeder` (7 roles, `module.action` permission naming):

| Role | Who this is | What they can do |
|---|---|---|
| **Super Admin** | System owner / IT | Everything — every permission in the system. |
| **HR Admin** | Head of HR | Full employee lifecycle: org masters, employee CRUD + sensitive fields, attendance, leave, expense visibility, tax verification, loans, onboarding, resignation/exit, F&F. No payroll processing. |
| **HR Executive** | HR team member | Employee add/edit (not delete), attendance/leave/expense view, tax proof verification, loan processing, onboarding, resignation handling. Narrower than HR Admin — no org-master editing, no sensitive-field editing. |
| **Payroll Admin** | Payroll team | Payroll processing end-to-end (process/finalize/reopen), sensitive employee data view (for salary purposes), loan management. Cannot touch leave/expense approvals or HR lifecycle actions. |
| **Finance Admin** | Finance team | Expense approval/processing, payroll finalization sign-off, loan approval, exit/F&F sign-off. |
| **Manager** | Anyone with direct reports | **No explicit permissions at all.** Visibility into their team is resolved dynamically through the reporting hierarchy (`Employee::directReports()` / `allSubordinateIds()`), not a permission grant — see §3. |
| **Employee** | Everyone else | **No explicit permissions either.** Sees only their own records via the same row-scoping mechanism. |

**Important nuance:** Manager and Employee roles are intentionally empty permission sets. A Manager doesn't "gain" access through a permission — every scoped resource (Leave, Attendance Regularization, Expense, Loan, Reports) independently checks "does this user have a real HR/Finance-tier permission? If not, do they have direct reports? If not, show only their own rows." This is why there's no separate "ESS view" to maintain — the same screen adapts to whoever is looking at it.

---

## 3. How row visibility actually works (read this before the menu map)

Almost every transactional resource (Leave, Attendance Regularization, Expense Claims, Loans, and Reports) is filtered through the same `ScopesToOwnTeam` rule:

```
Has the relevant "view all" permission (e.g. leave.view, expense.view)?
  → sees every employee's records
Else, does the user have at least one direct report?
  → sees their own records + their entire reporting-chain subordinates' records (Manager)
Else
  → sees only their own records (plain Employee)
```

So a Manager and an Employee both land on the exact same menu item (e.g. "Leave Applications") — the list is just pre-filtered. There's no hidden "My Leave" page; what you see under "Leave" *is* your view of it.

The two genuine exceptions with their own dedicated ESS pages are **My Salary Slips** and **My Tax Comparison** (see §5) — those are single-employee views by nature (a payslip is inherently "yours"), so they're separate pages rather than a scoped list.

---

## 4. Menu — as actually built

Grouped by Filament navigation group, in on-screen order. `[permission]` notes show what unlocks the *management* actions inside each screen — remember from §3 that most list pages are visible to everyone but row-scoped.

```
Pending Approvals                              ← always visible; empty inbox if nothing to act on
  Unified queue for every approval you're the current approver on
  (Leave / Attendance Regularization / Expense / Loan / Resignation — one screen, one queue)

Organization                                    [organization.manage to edit; organization.view to browse]
  Companies · Branches · Locations · Departments · Sub-Departments
  Designations · Grades · Cost Centers · Employee Types · Employment Types

Employees                                       [employee.view / .add / .edit / .delete / .view-sensitive]
  Employee Master (list/add/edit — tabbed form: Basic, Address, Employment, Statutory & Bank)
    Statutory/Bank tab fields are masked unless you hold employee.view-sensitive
  (Bulk Employee Upload is reached from inside the Employee list, not a separate menu item)

Onboarding                                      [onboarding.manage — HR-tier only]
  Onboarding Checklists (auto-computed completion % — not manually ticked)
  Employee Assets

Attendance
  Shifts · Holiday Calendar
  Attendance (daily records, manual + bulk import)
  Attendance Regularization (requests + approval, row-scoped per §3)

Leave
  Leave Types · Leave Balances
  Leave Applications (apply + approve, row-scoped per §3)

Expenses
  Expense Categories
  Expense Claims (submit + approve + Finance "Record Payment", row-scoped per §3)

Loans & Advances                                [loan.view / loan.manage]
  Employee Loans (request → manager → HR → finance approval → payroll recovery, row-scoped)

Payroll
  Salary Components
  Salary Structures                             (per-employee, versioned — never overwritten)
  Payroll Runs                                   [payroll.process / .finalize / .reopen]
  Monthly Payroll Inputs                         (bonus/incentive/arrears/adjustments)
  My Salary Slips                                ← ESS page, everyone sees only their own

Income Tax
  Financial Years · Tax Slabs · Regime Configuration
  Employee Regime · Investment Declarations       [tax.verify to approve/reject proofs]
  My Tax Comparison                              ← ESS page, old-vs-new regime, everyone's own

Exit Management                                 [resignation.manage / exit.manage / fnf.process]
  Resignations
  Exit Clearance
  Full & Final Settlement

Roles & Permissions
  Audit Logs                                     [audit.view — Super Admin only in the default seed]

Reports                                          (single page, not a resource)
  Month-picker + permission-gated report cards (Employee Master / Attendance / Leave /
  Expense / Payroll / Loans), each exports .xlsx. Team-scoped reports (Attendance/Leave/
  Expense/Loan) follow the same §3 rule; Employee Master and Payroll reports are HR/
  Payroll/Finance-tier only with no manager fallback.
```

**Simplified vs. the original plan:** the original spec sketched separate "Settings → Roles & Permissions / Users / Notification Templates" and dedicated ESS pages for every module ("My Attendance", "Apply Leave", "Submit Claim", "My Loans", "My Requests"). What actually got built consolidates all of that into the scoped-list pattern in §3, plus there's currently no in-app Roles/Users management screen — roles and permissions are managed by editing `RolesAndPermissionsSeeder` and re-running it, not through a UI. If a UI for that becomes a real need, it's the one gap between the plan and the build.

---

## 5. Employee Self-Service — what a plain Employee actually sees

Logging in as someone with the **Employee** role (no HR/Payroll/Finance permission, no direct reports):

- **Pending Approvals** — only if something of theirs needs *their own* action (e.g. sending back their own draft — rare); mostly empty for a plain employee.
- **Employees** — their own record only (view; edit only what HR has allowed self-service on, sensitive fields masked).
- **Attendance / Attendance Regularization** — their own attendance history; can submit a regularization request.
- **Leave / Leave Applications** — their own balance and applications; can apply for leave.
- **Expenses / Expense Claims** — their own claims; can submit a new one.
- **Loans & Advances** — their own loan/advance requests; can request one.
- **My Salary Slips** — download their own payslips (PDF).
- **Income Tax / Employee Regime, Investment Declarations, My Tax Comparison** — select regime, declare investments with proof upload, compare old vs. new regime tax.
- Everything under Organization, Onboarding (management side), Payroll processing, Exit Management, Roles & Permissions, Reports — **hidden or empty**, since none of those actions apply to a self-scoped, no-permission user.

## 6. Manager view — what's added on top of Employee

Same list as §5, plus every scoped screen (Attendance Regularization, Leave, Expense, Loan) now also shows **the manager's entire reporting chain**, not just their own row — and the approve/reject/send-back actions on **Pending Approvals** become live whenever a request from a direct or indirect report reaches the manager's level in that module's workflow chain (see §7). A Manager still doesn't see Organization masters, payroll processing, or HR-lifecycle actions unless they separately hold an HR/Finance/Payroll role.

---

## 7. Core Workflows (step by step)

### 7.1 Add a new employee
1. **Employees** → New Employee → fill Basic / Address / Employment tabs (Statutory & Bank only if you hold `employee.view-sensitive` / `employee.edit-sensitive`).
2. Set `reporting_manager_id` — the system rejects any assignment that would create a circular reporting chain (walks the chain before saving).
3. Save. The **Onboarding Checklist** for this employee is created automatically and its completion % recalculates live as documents, statutory details, bank details, department/designation/manager, salary structure, user login, and asset allocation are filled in elsewhere — nothing here is a manually-ticked box.
4. For bulk hiring, use the Bulk Employee Upload flow from inside the Employee list: **Download Template → Upload → per-row Validate (bad rows don't block good ones) → Preview → Confirm Import → Import Summary**. Salary/bank columns in the template are only honored for uploaders holding sensitive-data permission; otherwise those columns are rejected per-row rather than silently dropped.

### 7.2 Apply for & approve leave
1. Employee: **Leave → Leave Applications → New** — pick leave type, dates (or half-day), reason.
2. This creates an `approval_instances` row against the Leave workflow chain: **Employee → Reporting Manager → (HR, if the policy requires it)**.
3. Approvers act from **Pending Approvals** (or directly on the Leave Applications row): Approve / Reject / Send Back. Send Back returns it to the employee to edit and resubmit from level 1; Reject terminates it; Approve at the final level marks it approved and updates the leave balance/ledger.
4. Every action is permanently logged in `approval_actions` (and, for the create + every approve/reject/send-back, in the audit log too).

### 7.3 Attendance regularization
Same shape as leave: Employee raises a regularization request (missing punch / wrong in-out / WFH / on-duty / etc.) with old vs. requested values → **Reporting Manager → (HR, if configured)** → approve/reject/send-back through the same unified engine and Pending Approvals inbox.

### 7.4 Submit & approve an expense claim
1. Employee: **Expenses → Expense Claims → New** — add multiple line items (category, date, amount, bill/vendor details).
2. Chain: **Employee → Reporting Manager → Department Head (only if total amount exceeds the configured threshold) → Finance.** This conditional level-skip is a real feature of the generic workflow engine, not a special case for expenses.
3. Finance's final step after approval is a separate **Record Payment** action — this creates an immutable payment record; a claim structurally cannot be paid twice (DB-unique + service-level guard).

### 7.5 Request a loan / salary advance
Employee: **Loans & Advances → New** → chain **Employee → Reporting Manager → HR → Finance**. Once Finance gives final approval, the loan goes `active` with whatever `monthly_recovery` HR set — it starts auto-recovering from the employee's payroll the very next run (capped so it never pushes `outstanding_balance` below zero), and auto-closes when fully recovered.

> **Known gap worth knowing about:** if HR forgets to set `monthly_recovery` before Finance's final sign-off, the loan activates with a null recovery amount and silently recovers nothing until someone notices and fixes it. There's no validation guard against this today.

### 7.6 Run monthly payroll
1. **Payroll → Payroll Runs → New** — pick month + company. Only employees active/probation/notice-period as of that month are pulled in.
2. Generating the run **freezes that month's attendance** (edits blocked once frozen) and calculates, per employee: paid days / LOP (from weekly-off + holidays + attendance + approved leave), the latest applicable salary structure, approved payroll inputs (bonus/incentive/arrears/reimbursement/adjustments), active loan recoveries, statutory deductions (PF/ESIC/Professional Tax), and — fully automatically — that month's income-tax TDS from the employee's tax projection.
3. **Review** (`draft`/`calculated`) — Payroll Admin/Finance can inspect and recalculate freely at this stage.
4. **Finalize** → figures become immutable; **Lock** → fully closed; payslip PDFs generate.
5. **Reopen** (permission-gated, reason required, always audit-logged) unfreezes that run only for corrections — it never rewrites payslips already issued; a new superseding payslip is generated and the old one stays on record.
6. Employees see their own result immediately under **My Salary Slips**.

### 7.7 Income tax — regime & declarations
1. HR/Admin configures the **Financial Year** once, with Old/New regime slabs, standard deduction, rebate, surcharge brackets, and cess (all data-driven — a new budget's slab change never needs a code change).
2. Employee picks **Old or New regime** under Employee Regime (locked after the configured lock date unless HR reopens the window).
3. On the Old regime, employee declares investments (80C/80D/HRA/home loan interest/NPS/etc.) with proof uploads under Investment Declarations; HR/whoever holds `tax.verify` approves or rejects each with a capped amount.
4. **My Tax Comparison** always shows both regimes side by side regardless of which is selected, so the employee can actually compare before switching.
5. Every payroll run recalculates the employee's full-year tax projection (salary paid so far + projected remainder) and derives that month's TDS automatically — no manual step required unless a one-off correction is needed (`tds_adjustment` payroll input still exists for that).

### 7.8 Resignation → exit clearance → full & final settlement
1. Employee submits a **Resignation** (or HR does, on their behalf) — chain **Employee → Reporting Manager → HR**.
2. On submission, 5 **Exit Clearance** rows are auto-created (manager / IT / admin / finance / HR departments) — each department clears or rejects independently. *(Simplification: the "manager" clearance is currently completed by HR too, not routed to the actual manager — there's no dedicated manager-facing clearance screen yet.)*
3. Once cleared, HR/Finance run **Full & Final Settlement → Calculate**: leave encashment, loan/advance recovery, expense reimbursement, and notice-period shortfall recovery are all computed automatically from live data; pending salary, bonus, other earnings, TDS, and other deductions remain HR-editable manual lines.
4. **Approve → Mark Paid** — this is what actually sets the employee's status to `exited`.

### 7.9 Reports & audit
- **Reports** page: pick a month, click the card for the report you need (Employee Master / Attendance / Leave / Expense / Payroll / Loans) — downloads an `.xlsx`. Team-scoped reports follow the same visibility rule as §3; Employee Master and Payroll reports are HR/Payroll/Finance-tier only regardless of team.
- **Audit Logs** (Super Admin only by default): read-only before/after trail for every sensitive change — employee master edits, bank/statutory detail changes (logged as ciphertext, never plaintext, since those fields are encrypted at the model level), salary structure revisions, every workflow create/approve/reject/send-back across Leave/Regularization/Expense/Loan/Resignation, and every payroll finalize/lock/reopen.

---

## 8. Quick answers to "where do I go to…"

| I want to… | Go to… |
|---|---|
| Approve something waiting on me | **Pending Approvals** (works across every module) |
| See/download my payslip | **Payroll → My Salary Slips** |
| Compare old vs new tax regime | **Income Tax → My Tax Comparison** |
| Add a new joiner | **Employees → New Employee** (or Bulk Upload from the list) |
| Check onboarding progress for a new hire | **Onboarding → Onboarding Checklists** |
| Run this month's payroll | **Payroll → Payroll Runs → New** |
| See who reports to me | Dashboard **My Team Overview** widget, or any scoped list (Leave/Attendance/Expense/Loans) |
| Export a report | **Reports** |
| See who changed what and when | **Roles & Permissions → Audit Logs** (Super Admin only) |
| Change a role's permissions | Not in-app yet — edit `database/seeders/RolesAndPermissionsSeeder.php` and re-run it |

---

*This guide reflects the application as built and verified on 2026-07-24 (all 6 phases + Reports + Audit Log). If a later change adds a Roles/Permissions UI, dedicated manager exit-clearance screen, or other items flagged as gaps above, update this file alongside `ARCHITECTURE.md`'s Implementation Status section rather than letting the two drift apart.*
