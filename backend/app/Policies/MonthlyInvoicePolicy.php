<?php

namespace App\Policies;

use App\Models\MonthlyInvoice;
use App\Models\User;

class MonthlyInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('monthlyInvoice.view');
    }

    public function view(User $user, MonthlyInvoice $monthlyInvoice): bool
    {
        return $user->can('monthlyInvoice.view');
    }

    public function finalize(User $user, MonthlyInvoice $monthlyInvoice): bool
    {
        return $user->can('monthlyInvoice.finalize');
    }

    public function void(User $user, MonthlyInvoice $monthlyInvoice): bool
    {
        return $user->can('monthlyInvoice.void');
    }
}
