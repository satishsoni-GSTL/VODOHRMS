<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    private const TEMPLATES = [
        [
            'key' => 'birthday_self',
            'subject' => 'Happy Birthday!',
            'body' => "Happy Birthday, {employee_name}!\nWishing you a wonderful year ahead. Have a great day!",
            'placeholders_help' => 'Available: {employee_name}',
        ],
        [
            'key' => 'birthday_others',
            'subject' => "It's {employee_name}'s Birthday Today",
            'body' => "Today is {employee_name}'s birthday.\nTake a moment to wish them well!",
            'placeholders_help' => 'Available: {employee_name}',
        ],
        [
            'key' => 'work_anniversary_self',
            'subject' => 'Happy {years}-Year Work Anniversary!',
            'body' => "Congratulations, {employee_name}!\nToday marks {years} year(s) since you joined us. Thank you for your contributions!",
            'placeholders_help' => 'Available: {employee_name}, {years}',
        ],
        [
            'key' => 'work_anniversary_others',
            'subject' => "{employee_name}'s Work Anniversary Today",
            'body' => "Today is {employee_name}'s {years}-year work anniversary.\nTake a moment to congratulate them!",
            'placeholders_help' => 'Available: {employee_name}, {years}',
        ],
        [
            'key' => 'upcoming_holiday',
            'subject' => 'Upcoming Holiday: {holiday_name}',
            'body' => "Tomorrow, {holiday_date}, is a holiday: {holiday_name}.\nPlan your work accordingly.",
            'placeholders_help' => 'Available: {holiday_name}, {holiday_date}',
        ],
        [
            'key' => 'approval_action_required',
            'subject' => 'Approval required: {module} — {employee_name}',
            'body' => "{employee_name} ({employee_code}) has submitted a {module} request that needs your action.\nPlease review and act on this request at your earliest convenience.",
            'placeholders_help' => 'Available: {employee_name}, {employee_code}, {module}',
        ],
        [
            'key' => 'approval_outcome',
            'subject' => 'Your {module} request has been {outcome}',
            'body' => "Your {module} request has been {outcome}.\n{remarks_line}",
            'placeholders_help' => 'Available: {module}, {outcome}, {remarks_line} (empty unless remarks were given)',
        ],
        [
            'key' => 'payslip_ready',
            'subject' => 'Payslip ready — {month}',
            'body' => 'Your payslip for {month} has been finalized and is now available for download.',
            'placeholders_help' => 'Available: {month}',
        ],
        [
            'key' => 'welcome_account',
            'subject' => 'Your VODOHRMS login has been created',
            'body' => "Welcome, {name}! An account has been created for you on VODOHRMS.\nEmployee code: {employee_code}\nEmail: {email}\nTemporary password: {temp_password}\nYou will be required to change this password on your first login.",
            'placeholders_help' => 'Available: {name}, {employee_code}, {email}, {temp_password}',
        ],
        [
            'key' => 'exit_clearance_assigned',
            'subject' => 'Exit clearance needed: {department} — {employee_name}',
            'body' => "{employee_name} ({employee_code}) has resigned and requires {department} clearance.\nLast working date: {last_working_date}",
            'placeholders_help' => 'Available: {employee_name}, {employee_code}, {department}, {last_working_date}',
        ],
        [
            'key' => 'fnf_settlement',
            'subject' => '{event_label}',
            'body' => "{event_label}.\nFinal settlement amount: {final_amount}",
            'placeholders_help' => 'Available: {event_label}, {final_amount}',
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            NotificationTemplate::firstOrCreate(['key' => $template['key']], $template);
        }
    }
}
