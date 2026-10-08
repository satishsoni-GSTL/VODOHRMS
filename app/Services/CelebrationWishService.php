<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\NotificationTemplate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The personal birthday / work-anniversary wish for the celebrating employee — sent as a
 * push at 9 AM (hr:send-celebration-wishes) and shown as a card on their app home screen
 * all day. Wording is editable by HR in Notification Templates:
 *   birthday_wish            — {first_name}, {employee_name}
 *   work_anniversary_wish    — {first_name}, {employee_name}, {years}
 * (subject = title, body = message).
 */
class CelebrationWishService
{
    public const TYPE_BIRTHDAY = 'birthday';

    public const TYPE_ANNIVERSARY = 'anniversary';

    private const DEFAULTS = [
        'birthday_wish' => [
            'subject' => 'Happy Birthday, {first_name}! 🎂',
            'body' => 'Wishing you a fantastic year ahead, filled with happiness, good health and success. Have a wonderful day! — Team GlobalSpace',
        ],
        'work_anniversary_wish' => [
            'subject' => 'Happy {years}-Year Work Anniversary, {first_name}! 🎉',
            'body' => 'Thank you for {years} wonderful year(s) with GlobalSpace. Your hard work and dedication make a real difference — here\'s to many more! — Team GlobalSpace',
        ],
    ];

    /**
     * Today's wishes for $employee (birthday and/or anniversary).
     *
     * @return Collection<int, array{type: string, title: string, message: string, years: ?int}>
     */
    public function wishesFor(Employee $employee, ?CarbonInterface $date = null): Collection
    {
        $today = Carbon::parse($date ?? now())->startOfDay();
        $wishes = collect();
        $firstName = trim((string) $employee->first_name) ?: trim(preg_replace('/\s+/', ' ', (string) $employee->full_name));
        $fullName = trim(preg_replace('/\s+/', ' ', (string) $employee->full_name));

        if ($employee->dob && $this->isAnniversaryOf($employee->dob, $today)) {
            $wishes->push(['type' => self::TYPE_BIRTHDAY, 'years' => null]
                + $this->render('birthday_wish', ['{first_name}' => $firstName, '{employee_name}' => $fullName]));
        }

        if ($employee->date_of_joining && $this->isAnniversaryOf($employee->date_of_joining, $today)) {
            $years = $today->year - $employee->date_of_joining->year;

            if ($years >= 1) {
                $wishes->push(['type' => self::TYPE_ANNIVERSARY, 'years' => $years]
                    + $this->render('work_anniversary_wish', ['{first_name}' => $firstName, '{employee_name}' => $fullName, '{years}' => (string) $years]));
            }
        }

        return $wishes;
    }

    /** Same day and month (29 Feb celebrated on 28 Feb in non-leap years). */
    private function isAnniversaryOf(CarbonInterface $original, Carbon $today): bool
    {
        $day = $original->month === 2 && $original->day === 29 && ! $today->isLeapYear() ? 28 : $original->day;

        return $original->month === $today->month && $day === $today->day;
    }

    /** @return array{title: string, message: string} */
    private function render(string $key, array $replacements): array
    {
        $template = NotificationTemplate::where('key', $key)->first();

        return [
            'title' => strtr($template?->subject ?: self::DEFAULTS[$key]['subject'], $replacements),
            'message' => strtr(trim($template?->body ?: self::DEFAULTS[$key]['body']), $replacements),
        ];
    }
}
