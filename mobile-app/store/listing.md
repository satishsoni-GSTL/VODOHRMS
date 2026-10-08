# Google Play listing — VODO HRMS

Paste these into **Play Console → Grow → Store presence → Main store listing**.

> **Rule for this listing:** every line must be something a reviewer can see in the uploaded
> build, using the review login you give Google (App content → App access). Manager features
> are only visible to an account that has people reporting to it.

---

## App name (30 characters max)

```
VODO HRMS
```

## Short description (80 characters max)

This one is 70 characters:

```
Attendance, leave, WFH, expenses & payslips for GlobalSpace employees.
```

## Full description

This version (about 2,000 characters) matches build 1.0.0 (3) and later, which include push notifications:

```
VODO HRMS is the official HR app for GlobalSpace employees. Sign in with the employee code and password provided by GlobalSpace HR and handle everyday HR tasks from your phone.

ATTENDANCE
• Monthly attendance, colour-coded day by day: present, work from home, half day, leave, holiday, weekly off and absent.
• Check-in and check-out times and working hours for each day, with monthly totals.
• Request an attendance regularization for a missed or wrong punch.
• On an approved Work From Home day, clock in and clock out from the home screen.

LEAVE & WORK FROM HOME
• Leave balance for each leave type.
• Apply for full-day or half-day leave; the form shows how many working days the request will use.
• Attach a document or photo to a request.
• Request Work From Home in advance.
• See the status and approver remarks for every request, and reapply if a request was rejected or sent back.

EXPENSES
• Submit expense claims with one or more items and attach a photo or PDF of each bill.
• Track each claim's status and approval history.
• Download a monthly expense statement (PDF) with all your expenses and bills.

SALARY & BENEFITS
• Salary slips with earnings, deductions and net pay; download the payslip PDF.
• Request a loan or salary advance and track its status.

HOLIDAYS & POLICIES
• Company holiday calendar; claim optional holidays.
• View and download HR policy documents.

FOR MANAGERS (shown only to employees who have team members reporting to them)
• Approve, reject or send back requests from your team.
• See which team members are present, working from home, on leave or not yet in, for any day, and open each member's monthly attendance.
• View your team's leave and requests by month.

NOTIFICATIONS
• Get notified about requests waiting for your approval, request decisions and new payslips.

SECURITY
• Sign in once on your phone; sign out at any time.
• Data is sent over an encrypted connection.

VODO HRMS is for GlobalSpace employees only. Accounts are created by GlobalSpace HR.
```

Build 1.0.0 (2) had no push notifications. If you ever list a build without Firebase keys
again, remove the NOTIFICATIONS section.

---

## Review login for Google (App content → App access)

Choose *All or some functionality is restricted* and give a **manager** login. Otherwise the
"For managers" features in the description can't be found, and the app is rejected for
"Misleading claims".

The review account needs:
- **at least one team member reporting to it**, so the Team tab appears;
- **a pending request from that team member**, so Approvals is not empty;
- some attendance days, one leave application, one expense claim and one payslip of its own.

Text to paste in the instructions box:

```
Sign in with Employee Code: <code>  Password: <password>
This account is a manager: the "Team" tab (bottom bar) shows Approvals, today's team attendance, team leave and team requests.
Home shows attendance, leave balance and quick actions. Expense Claims → PDF icon downloads the monthly expense statement.
```

---

## Other listing fields

| Field | Value |
|---|---|
| App category | Business |
| Contact email | your HR or IT support address |
| Website | `https://hrms.globalspace.in` |
| Privacy policy | `https://hrms.globalspace.in/privacy-policy` |

## Graphics (all in this folder)

| Asset | File |
|---|---|
| App icon (512×512) | `play-icon-512.png` |
| Feature graphic (1024×500) | `feature-graphic-1024x500.png` |
| Phone screenshots | `screenshots/` |
