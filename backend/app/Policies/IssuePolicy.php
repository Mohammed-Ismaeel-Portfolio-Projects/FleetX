<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;

/**
 * Issue authorization.
 *
 * Uses dotted Gate names (issue.*) to match DriverPolicy / ShipmentPolicy conventions.
 */
class IssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('issue.view');
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->can('issue.view');
    }

    public function create(User $user): bool
    {
        return $user->can('issue.create');
    }

    public function update(User $user, Issue $issue): bool
    {
        return $user->can('issue.update');
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $user->can('issue.delete');
    }
}
