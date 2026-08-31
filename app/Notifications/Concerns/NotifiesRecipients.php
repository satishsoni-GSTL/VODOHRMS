<?php

namespace App\Notifications\Concerns;

use App\Models\Employee;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

trait NotifiesRecipients
{
    private function notifyEmployee(Employee $employee, Notification $notification): void
    {
        if ($employee->user) {
            $this->sendSafely($employee->user, $notification);

            return;
        }

        $email = $employee->official_email ?: $employee->personal_email;

        if ($email) {
            $this->sendSafely(NotificationFacade::route('mail', $email), $notification);
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
            $this->sendSafely($user, $notification);
        }
    }

    /**
     * Notifications (email in particular) run synchronously here and are frequently fired from
     * inside a DB transaction (e.g. submitting an expense claim / WFH request). A broken mail
     * transport must never roll back or fail the underlying business action — so send best-effort
     * and log, rather than letting a transport exception bubble up.
     */
    private function sendSafely(mixed $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Notification delivery failed', [
                'notification' => $notification::class,
                'notifiable' => is_object($notifiable) ? $notifiable::class : gettype($notifiable),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
