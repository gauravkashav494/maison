<?php

namespace App\Policies;

use App\Contracts\AdminIdentity;
use App\Models\ClientUser;
use App\Models\PlatformActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * The activity log is read-only.
 *
 *   /admin   a Super Admin sees everyone's lines, platform staff only their own
 *            (PlatformActivityResource::getEloquentQuery)
 *   /client  a Client Admin sees the lines of their client's staff, other staff only their
 *            own (ActivityLogResource::getEloquentQuery)
 *
 * Nobody can add, change or delete a line from a panel; old lines are pruned after
 * PlatformActivity::KEEP_MONTHS.
 */
class PlatformActivityPolicy
{
    public function viewAny(AdminIdentity $user): bool
    {
        return $user->isActiveAccount();
    }

    public function view(AdminIdentity $user, PlatformActivity $line): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->isPlatformAccount()) {
            return $user->isSuperAdmin() || $this->isOwn($user, $line);
        }

        // A record's history tab shows every staff member's lines to whoever may open the record.
        return $user instanceof ClientUser
            && (int) $line->client_id === (int) $user->client_id
            && $line->actor_model === 'ClientUser';
    }

    private function isOwn(AdminIdentity $user, PlatformActivity $line): bool
    {
        return $user instanceof Model && $line->actor_model === class_basename($user) && (int) $line->actor_id === (int) $user->getKey();
    }
}
