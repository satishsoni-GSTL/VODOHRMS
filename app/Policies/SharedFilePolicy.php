<?php

namespace App\Policies;

use App\Models\SharedFile;
use App\Models\User;

/**
 * Any portal user can share their own files; HR with fileshare.manage can oversee everyone's.
 */
class SharedFilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SharedFile $sharedFile): bool
    {
        return $this->ownsOrManages($user, $sharedFile);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SharedFile $sharedFile): bool
    {
        return $this->ownsOrManages($user, $sharedFile);
    }

    public function delete(User $user, SharedFile $sharedFile): bool
    {
        return $this->ownsOrManages($user, $sharedFile);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    private function ownsOrManages(User $user, SharedFile $sharedFile): bool
    {
        return $sharedFile->owner_id === $user->id || $user->can('fileshare.manage');
    }
}
