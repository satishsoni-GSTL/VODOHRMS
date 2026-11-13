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

The genuine exceptions with their own dedicated ESS pages are **My Salary Slips**, **My Tax Comparison**, and **My Form 16** (see §5) — those are single-employee views by nature (a payslip is inherently "yours"), so they're separate pages rather than a scoped list.

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
  Employee Regime table has a Fix/Lock and Unlock row action [tax.manage] — once fixed,
  the employee can no longer self-change their regime for that financial year
  Generate Form 16                                [tax.manage — per-employee or bulk]
  My Tax Comparison                              ← ESS page, old-vs-new regime, everyone's own
  My Form 16                                     ← ESS page, download own Form 16 PDF

Exit Management                                 [resignation.manage / exit.manage / fnf.process]
  Resignations
  Exit Clearance
  Full & Final Settlement

File Sharing
  My Shared Files                                [everyone: own files; fileshare.manage (HR Admin) sees all]
  Shared With Me                                 [everyone: files shared to your login email]

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
- **Income Tax / Employee Regime, Investment Declarations, My Tax Comparison, My Form 16** — select regime (until HR fixes/locks it for the year), declare investments with proof upload, compare old vs. new regime tax, download their own Form 16 once HR has generated it.
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

### 7.10 Share a file with internal or external people
1. **File Sharing → My Shared Files → Share a file** — upload the file, add a title and an optional message, set an optional expiry, and choose whether recipients may download it.
2. Under **Share with**, add one row per person: pick an internal user (fills email/name) or type any external email. Untick **Can comment** for view-only people.
3. Save. Each person is emailed their own private link on `file.globalspace.in`. Opening it asks them to request a **6-digit code**, which is emailed to that same address (valid 10 minutes, 5 wrong tries max, 3 codes per 10 minutes). A forwarded link is useless without access to that mailbox.
4. Once verified, they can preview (PDF, images, audio/video, text), download (if allowed), and post comments or replies. Verification lasts 12 hours in that browser.
5. The owner sees everything on the file's page: **People with access** (add person, resend invite, copy link, revoke/restore), **Comments** (add comment, reply — everyone in the thread is emailed), and **Activity** (codes sent, wrong codes, views, downloads, with IP).
6. **Edit** the file to change the message/expiry/download setting, or turn **Sharing enabled** off to kill every link at once. Deleting the file removes the stored copy too.
7. Internal users also find files shared with them under **File Sharing → Shared With Me** — opening one still goes through the email-code check.

### 7.11 Organogram (every company)
**Employees → Organogram** draws the reporting-manager tree as a **top-down chart**. HR (`employee.view`) sees the whole organisation and can filter by company and department; anyone else sees themselves (highlighted, "You") and their own team, plus their reporting line above them.
- **Cards** show photo or initials, name, designation, code and department; the colour stripe is the department (legend at the top). Notice period = amber outline; vacant seats (Field Positions view) = red.
- **+N / −** under a card shows or hides that person's team instantly. Small charts (≤ 40 people) open fully; larger ones open two levels — use **Expand all** / **Collapse**. A team whose members have nobody under them is stacked in a column to keep the chart narrow.
- **Zoom** (−, +, ⤢ fit to screen) at the top-left of the chart; **drag** the background to move around.
- Hover a card: **◎ Focus** shows only that branch (with a breadcrumb back up to the whole organisation); **↗** opens the employee record (HR).
- **Search** highlights matching people and keeps the path down to them. **List** switches to the indented list (handy on phones). **Print** prints just the chart.

### 7.12 Pharma / Field Force (HO + field staff in one portal)
Nothing here appears until a company has **Organization → Companies → Field Force (Pharma) enabled** switched on. Corporate companies are untouched. Needs `fieldforce.view` (HR Executive) or `fieldforce.manage` (HR Admin).

1. **Field Force → Geography Levels → Load standard pharma levels** — adds Zone › Region › Area › Headquarter › Territory and the NSM › ZBM › RBM › ABM › MR ladder (only missing ones; you can rename or add levels afterwards).
2. **Divisions** — therapy/sales divisions (Cardio, Derma, Gynae…).
3. **Geography** — build the tree: each node picks its level and its parent one level up. Mark HQs/territories as HQ, Ex-HQ or Outstation for allowance purposes.
4. **Hierarchy Levels** — rank 1 is most senior; "Covers geography level" says what one person at that rung is responsible for (ABM → Area, MR → HQ).
5. **Positions & Vacancies** — create one position (seat) per territory/manager: level, division, geography and which position it reports to. Seats exist with or without a person, so vacant territories stay visible (red **VACANT**, with days vacant). Filter *Vacancy → Vacant only* for the vacancy report.
6. **Assign** a person to a seat (joining, transfer, promotion). If they already hold another seat, that seat becomes vacant — that is a transfer. The employee's division, HQ and workforce type are copied from the seat. With *Update reporting managers* on (default), their reporting manager becomes the holder of the nearest **filled** seat above, so leave/expense/WFH approvals follow the field hierarchy — an MR under a vacant ABM seat reports to the RBM until the ABM seat is filled. **Vacate** re-points the team to the next manager up. Every move is kept in the position's **Assignment History**.
7. Head-office staff don't need positions: set **Workforce Type = Head Office** on the employee's **Field Force** tab and keep using the normal reporting manager.
8. **Organogram** gains two more views: **Field Positions** (seat tree with vacancies; a field manager without HR rights sees only their own part) and **Geography** (filled/total seats per node).

### 7.13 Geo-tagged attendance (mobile app punch)
The app's Clock In / Clock Out records the phone's GPS position on every punch. Who may punch from the app on a given day:

| Who | Rule |
|---|---|
| Anyone on an approved **Work From Home** day | Allowed; location recorded if the phone sends it, not checked. |
| **Field Force** staff (Workforce Type = Field) | Allowed from anywhere; location is **mandatory**. |
| Office staff whose **Location** has a geofence | Allowed only **inside the radius**; location mandatory. Outside → refused with the distance shown. |
| Everyone else | Not allowed — use the biometric device. |

Nobody can punch on a day already marked Leave, or on a frozen (payroll-locked) day.

- **Set up a geofence:** **Organization → Locations → Edit → Attendance geofence** — enter the office latitude/longitude (in Google Maps, right-click the office → click the coordinates to copy) and a radius in metres (200 m is a sensible start; GPS indoors can be off by 30–50 m).
- **See where someone punched:** **Attendance → Attendance** → row action **Punches** — time, source, coordinates (click to open in Google Maps), GPS accuracy, and for office punches the distance from the office.

### 7.14 Probation confirmation
1. **Employees → Probation Due** lists everyone with status *Probation*, their probation end date (date of joining + probation period; 180 days if none is set) and days left — red when overdue. HR sees everyone; a manager sees their own reporting line. The menu badge counts those ending within 30 days.
2. Reporting managers and HR are reminded automatically (email + app push) **30, 15, 7 and 0 days** before the end date, then weekly while overdue, until a review is submitted.
3. **Start review** → recommend **Confirm** (optionally a different effective date) or **Extend probation** by N days, with a rating, a performance summary and (required for an extension) areas for improvement.
4. The review goes to **HR** for approval through the standard engine (Pending Approvals, web or app). Approve / Reject / Send Back outcomes go to the manager who submitted it — the employee is not told about an unapproved review.
5. On final approval: **Confirm** sets the employee to *Active* with the confirmation date; **Extend** adds the days to their probation period (they stay on probation and reappear in Probation Due later). A **confirmation / extension letter** PDF (with the company/document logo) is generated, emailed to the employee and downloadable from the review (**Letter**).
6. Approval levels can be changed like any other workflow (module `probation_confirmation`); the default is a single HR level.

### 7.15 Travel requests and advances
1. Employee (web: **Expenses → Travel Requests → New**, or the app: **More → Travel requests**): from/to, dates, mode, estimated cost, and an optional **advance**. Trips must be requested before departure; overlapping trips are refused.
2. Approval: **Reporting Manager**, plus **Finance** only when an advance is requested (a conditional workflow level — module `travel`). Pending or sent-back requests can be cancelled by the employee.
3. Finance (`expense.process`) records the advance with **Pay Advance** (amount up to the approved advance, date, UTR).
4. After the trip, the employee files an **Expense Claim** and picks the trip under **For trip**. When Finance records the claim payment, the trip's unadjusted advance is netted off first — the payment form defaults to *approved amount − advance* and says how much advance was adjusted.
5. **Close Trip** once settled. If part of the advance is still unadjusted (the trip cost less than the advance), a remark on how it was recovered is required.

### 7.16 Onboarding tasks
1. **Onboarding → Onboarding Task Templates** (`onboarding.manage`) is the list of what has to happen for every joiner: who does it (HR, the reporting manager, the joiner, everyone with a role, or a named person such as your IT/Admin owner) and when (days before/after joining; may be limited to one company). A standard set of 12 is pre-loaded — reassign the IT/Admin ones to the right people.
2. When an employee is added with a joining date in the last 30 days or in the future, their tasks are created automatically and each owner gets one email/push listing their tasks. For anyone else HR can click **Start Tasks** on the onboarding checklist.
3. **Onboarding → Onboarding Tasks**: owners see only their tasks (menu badge = due within 3 days) and click **Mark Done**; HR sees all, can add one-off tasks, **Skip** (with a reason) or **Reopen**. The joiner's own tasks show up once they log in.
4. Owners are reminded the day after a task goes overdue, and every Monday about everything still overdue. The checklist shows *done / total* tasks per joiner.

### 7.17 Performance management (PMS)
1. HR (`employee.edit`): **Performance → Appraisal Cycles → New** — name, review period, optional company, due dates, minimum goals. **Launch** creates an appraisal for every active/probation/notice-period employee (reviewer = their reporting manager; HR can **Change Reviewer**) and asks them to set goals.
2. Employee: **Performance → Appraisals → Open** — add goals (KRAs) with a target and weightage; weightages must total **100%**. **Submit Goals**.
3. Manager: can adjust, then **Approve Goals** or **Return Goals** with remarks.
4. HR: **Start Review** on the cycle. Employees rate themselves 1–5 on each approved goal with comments → **Submit Self Review**. Managers then rate each goal → **Submit Review**. The weighted score (Σ rating × weight) becomes the final rating; the employee sees the manager's ratings only after submission.
5. HR may **Calibrate Rating** (with a reason) before **Close Cycle**, which finalises every reviewed appraisal.
6. The menu badge counts appraisals waiting on you (your goals / self review, or your team's approvals / reviews). The cycle list shows progress (goals approved, reviewed).
7. **Exports (Excel):** **Export** on a cycle row, **Export to Excel** on the Appraisals list, or **Reports → Performance Appraisals** (pick the cycle). Three sheets: **Summary** (one row per employee — reviewer, stage, goals, self / manager / final rating, band, HR calibration note, stage dates), **Goals** (every goal with weight, ratings and comments) and **Distribution** (employees per rating band by department, with averages). HR gets everyone; a manager gets their reporting line. PMS is web-only (not in the mobile app).

### 7.18 Pre-joining portal (new hires, before day one)
1. Add the new hire under **Employees → New** with their joining date (status *Probation*).
2. Send the link: **Onboarding → Pre-joining → Invite New Hire**, or **Send Pre-joining Link** on the employee's row. It goes to their personal email, is valid 21 days, and any older link for that person stops working. (Not offered for anyone who joined more than 30 days ago — they use My Profile.)
3. The new hire opens the link, asks for a **6-digit code** emailed to the same address (valid 10 minutes, 5 wrong tries, 3 codes per 10 minutes), and fills in: personal details, contact, current & permanent address, emergency contact, PAN / Aadhaar / UAN, salary bank account, and uploads documents (PAN card, Aadhaar card, address proof, highest education certificate, cancelled cheque — plus optional relieving letter, payslips, passport). **Save & finish later** keeps a draft; **Submit to HR** needs every required detail (missing documents are allowed but stay flagged).
4. HR is notified. **Onboarding → Pre-joining** shows each hire's % complete and status (menu badge = submissions to review). **Details** opens the employee record — check the data and, on the **Documents** tab, open and **Verify** each document. Then **Mark Reviewed**, or **Send Back** with what to correct (the hire is emailed and the same link works again). **Resend Link** / **Revoke** as needed.
5. Everything the hire enters is written straight onto their employee record, so nothing has to be re-typed on day one.

### 7.19 My Profile and profile completion (every employee)
- The **Dashboard** shows a **profile completion** card for every employee: overall %, each section (Personal, Contact, Address, Emergency contact, Statutory IDs, Bank account, Documents) and exactly what is missing, with **Fill missing details**.
- **Employees → My Profile** (menu badge shows the % while incomplete): fill in missing details and **Upload document**. Rules:
  - Contact, address, marital status, blood group and emergency contact can always be updated.
  - Name, date of birth, gender, PAN, Aadhaar, UAN and the salary bank account can be **filled in when empty** but not changed once on file — HR changes them (on the employee record). Saved Aadhaar / account numbers are shown masked.
  - Formats are checked (PAN ABCDE1234F, 12-digit Aadhaar / UAN, IFSC, 10-digit mobile, 6-digit PIN).
  - An employee can remove their own document until HR verifies it.
- HR sees and manages every employee's documents on the employee record's **Documents** tab (needs `employee.view-sensitive` / `onboarding.manage` to see, `employee.edit-sensitive` / `onboarding.manage` to upload, verify or delete). The onboarding checklist's *Docs* tick follows these uploads.

### 7.20 Celebration themes (Diwali, New Year, Annual Day, Birthday…)
**Organization → Celebration Themes** (`organization.manage`) dresses up the login page and dashboard for an occasion: themed colours across the portal, an animated backdrop behind the sign-in card with a greeting and countdown, a banner on the dashboard, and a short animation the first time someone opens the dashboard after signing in.

| Theme | Look | Default dates |
|---|---|---|
| Diwali | Diyas, rangoli, lanterns, fireworks | 1–12 Nov 2026 |
| New Year | Midnight blue & gold, fireworks, the new year in gold | 29 Dec – 2 Jan |
| Christmas | Red & green, falling snow, tree | 20–26 Dec |
| Annual Day | Purple & gold, confetti, trophy, event date & venue | none — set them |
| Birthday | Pink, balloons & confetti, cake | personal (see below) |
| Holi | Colour-powder bursts | 19–23 Mar 2027 |
| Independence Day | Tricolour confetti, national flag | 13–16 Aug 2027 |
| Republic Day | Tricolour confetti, national flag | 24–27 Jan 2027 |

1. Flip **On** in the list (or **Edit** to set dates). A theme shows only while it is on **and** today is between *Show from* and *Show until*; with no dates it stays on until you switch it off. Only Diwali is on initially — every other theme starts off.
2. **Edit** also lets you change the greeting title and message (placeholders `{name}`, `{company}`, `{year}`, `{venue}`; empty = the theme's default wording), the **countdown days** ("5 days to Annual Day"), and for Annual Day the **venue**.
3. Only one company-wide theme shows at a time. If two overlap, the one whose dates started most recently wins (a dated theme beats one with no dates); the list shows *Live now* / *Overlaps* / *Scheduled* / *Ended*.
4. **Birthday is personal:** while it's on, each employee gets the birthday look and "Happy Birthday, {name}!" on their own birthday (from the date of birth on their profile), over any other theme. It never appears on the login page.
5. **Preview** shows a theme on your own dashboard only (it says *Preview — only you can see this*; *End preview* on the banner or at the top of the list). **Login page** opens the sign-in page with the theme.
6. Emergency off switch for every theme without a deploy: set `FESTIVE_THEMES=false` in `.env`.

---

## 8. Quick answers to "where do I go to…"

| I want to… | Go to… |
|---|---|
| Approve something waiting on me | **Pending Approvals** (works across every module) |
| See/download my payslip | **Payroll → My Salary Slips** |
| Compare old vs new tax regime | **Income Tax → My Tax Comparison** |
| Download my Form 16 | **Income Tax → My Form 16** |
| Generate Form 16 for employees | **Income Tax → Generate Form 16** [tax.manage] |
| Fix/lock (or unlock) an employee's tax regime | **Income Tax → Employee Regime** → Fix / Lock or Unlock row action [tax.manage] |
| Add a new joiner | **Employees → New Employee** (or Bulk Upload from the list) |
| Check onboarding progress for a new hire | **Onboarding → Onboarding Checklists** |
| Run this month's payroll | **Payroll → Payroll Runs → New** |
| See who reports to me | Dashboard **My Team Overview** widget, or any scoped list (Leave/Attendance/Expense/Loans) |
| See the org chart | **Employees → Organogram** |
| Find vacant territories | **Field Force → Positions & Vacancies** → filter Vacancy: Vacant only |
| Transfer / promote a field employee | **Field Force → Positions & Vacancies** → Assign on the new seat |
| Request a trip / travel advance | **Expenses → Travel Requests** (or the app: More → Travel requests) |
| See onboarding work assigned to me | **Onboarding → Onboarding Tasks** |
| Run an appraisal cycle | **Performance → Appraisal Cycles** |
| Export appraisal results | **Reports → Performance Appraisals**, or **Export** on the cycle |
| Get a new hire to fill in their details before joining | **Onboarding → Pre-joining → Invite New Hire** |
| Fill in my missing details / upload my documents | **Employees → My Profile** (or the Dashboard card) |
| Switch on a festival / Annual Day / birthday theme | **Organization → Celebration Themes** |
| See / verify an employee's documents | **Employees → (employee) → Documents** tab |
| Set my goals / do my self review | **Performance → Appraisals** |
| Confirm or extend someone's probation | **Employees → Probation Due** → Start review |
| Let office staff punch from the app | **Organization → Locations** → set the geofence |
| See where a punch was made | **Attendance → Attendance** → Punches |
| Export a report | **Reports** |
| See who changed what and when | **Roles & Permissions → Audit Logs** (Super Admin only) |
| Change a role's permissions | Not in-app yet — edit `database/seeders/RolesAndPermissionsSeeder.php` and re-run it |

---

*This guide reflects the application as built and verified on 2026-08-10 (all 7 phases + Reports + Audit Log). If a later change adds a Roles/Permissions UI, dedicated manager exit-clearance screen, or other items flagged as gaps above, update this file alongside `ARCHITECTURE.md`'s Implementation Status section rather than letting the two drift apart.*
