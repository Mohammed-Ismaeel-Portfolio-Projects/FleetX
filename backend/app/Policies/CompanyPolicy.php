<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/**
 * Company Policy — ops gates unchanged; client may show own linked company only.
 */
class CompanyPolicy
{
    public function view(User $user): bool
    {
        return $user->can('company.view');
    }

    public function create(User $user): bool
    {
        return $user->can('company.create');
    }

    public function show(User $user, Company $company): bool
    {
        if ($user->can('company.view')) {
            return true;
        }

        if ($user->role?->name !== 'client') {
            return false;
        }

        $linkedId = $user->company_id;

        return $linkedId !== null && (string) $linkedId === (string) $company->id;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('company.update');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('company.delete');
    }

    public function restore(User $user): bool
    {
        return $user->can('company.restore');
    }

    public function toggleStatus(User $user, Company $company): bool
    {
        return $user->can('company.toggle-status');
    }
}
