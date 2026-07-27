<?php

namespace App\Policies;

use App\Models\NotificationTemplate;
use App\Models\User;

class NotificationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notification.manage');
    }

    public function view(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can('notification.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can('notification.manage');
    }

    public function delete(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return false;
    }
}
