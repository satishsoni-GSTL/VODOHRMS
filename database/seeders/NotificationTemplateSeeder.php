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
            'key' => 'birthday_wish',
            'subject' => 'Happy Birthday, {first_name}! 🎂',
            'body' => 'Wishing you a fantastic year ahead, filled with happiness, good health and success. Have a wonderful day! — Team GlobalSpace',
            'placeholders_help' => 'Mobile app wish to the birthday person at 9 AM (subject = title). Available: {first_name}, {employee_name}',
        ],
        [
            'key' => 'work_anniversary_wish',
            'subject' => 'Happy {years}-Year Work Anniversary, {first_name}! 🎉',
            'body' => "Thank you for {years} wonderful year(s) with GlobalSpace. Your hard work and dedication make a real difference — here's to many more! — Team GlobalSpace",
            'placeholders_help' => 'Mobile app wish to the employee at 9 AM on their work anniversary (subject = title). Available: {first_name}, {employee_name}, {years}',
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
            'key' => 'password_reset',
            'subject' => 'Your VODOHRMS password has been reset',
            'body' => "Hi {name}, your password on VODOHRMS was reset by an administrator.\nEmployee code: {employee_code}\nEmail: {email}\nNew password: {new_password}\nYou may be required to change this password on your next login.",
            'placeholders_help' => 'Available: {name}, {employee_code}, {email}, {new_password}',
        ],
        [
            'key' => 'password_reset_otp',
            'subject' => 'Your VODOHRMS password reset code',
            'body' => "Hi {name}, use this code to reset your VODOHRMS password:\n{otp}\nThis code expires in {minutes} minutes.\nIf you didn't request this, you can ignore this email.",
            'placeholders_help' => 'Available: {name}, {otp}, {minutes}',
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
        [
            'key' => 'shared_file_invitation',
            'subject' => '{owner_name} shared "{file_title}" with you',
            'body' => "Hi {recipient_name}, {owner_name} has shared a file with you.\nFile: {file_title} ({file_name})\n{message}\nYou will be asked to confirm a one-time code sent to this email address before the file opens.\n{expiry_line}",
            'placeholders_help' => 'Link button is added automatically. Available: {recipient_name}, {owner_name}, {file_title}, {file_name}, {message}, {expiry_line}',
        ],
        [
            'key' => 'shared_file_otp',
            'subject' => 'Your access code for "{file_title}"',
            'body' => "Hi {recipient_name}, use this code to open the file shared with you:\n{otp}\nThis code expires in {minutes} minutes.\nIf you didn't request this, you can ignore this email.",
            'placeholders_help' => 'Available: {recipient_name}, {file_title}, {otp}, {minutes}',
        ],
        [
            'key' => 'shared_file_comment',
            'subject' => 'New {comment_kind} on "{file_title}"',
            'body' => "{author_name} posted a {comment_kind} on \"{file_title}\":\n\"{comment_excerpt}\"",
            'placeholders_help' => 'Link button is added automatically. Available: {author_name}, {comment_kind} (comment/reply), {file_title}, {comment_excerpt}',
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $template) {
            NotificationTemplate::firstOrCreate(['key' => $template['key']], $template);
        }
    }
}
