<?php

namespace App\Notifications\Concerns;

use App\Models\Employee;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

trait NotifiesRecipients
{
    private function notifyEmployee(Employee $employee, Notification $notification): void
    {
        if ($employee->user) {
            $employee->user->notify($notification);

            return;
        }

        $email = $employee->official_email ?: $employee->personal_email;

        if ($email) {
            NotificationFacade::route('mail', $email)->notify($notification);
        }
    }

    /**
     * @param  iterable<int, \App\Models\User|null>  $users
     */
    private function notifyUsers(iterable $users, Notification $notification): void
    {
        $seen = [];

        foreach ($users as $user) {
            if (! $user || isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;
            $user->notify($notification);
        }
    }
}
