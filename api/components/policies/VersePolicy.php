<?php

namespace app\components\policies;

use app\modules\v1\models\User;
use app\modules\v1\models\Verse;

class VersePolicy
{
    /**
     * Determine if the given verse can be viewed by the user.
     *
     * @param User|null $user
     * @param Verse $verse
     * @return bool
     */
    public function canView(?User $user, Verse $verse): bool
    {
        // Public verses can be viewed by anyone
        if ($verse->status === 1) { // Assuming 1 means Public
            return true;
        }

        if (!$user) {
            return false;
        }

        // Owner can always view
        if ($verse->author_id === $user->id) {
            return true;
        }

        // TODO: Add group member check logic here if needed
        return false;
    }

    /**
     * Determine if the given verse can be updated by the user.
     *
     * @param User $user
     * @param Verse $verse
     * @return bool
     */
    public function canUpdate(User $user, Verse $verse): bool
    {
        // Only owner can update for now
        return $verse->author_id === $user->id;
    }

    /**
     * Determine if the given verse can be deleted by the user.
     *
     * @param User $user
     * @param Verse $verse
     * @return bool
     */
    public function canDelete(User $user, Verse $verse): bool
    {
        // Only owner can delete
        return $verse->author_id === $user->id;
    }
}
