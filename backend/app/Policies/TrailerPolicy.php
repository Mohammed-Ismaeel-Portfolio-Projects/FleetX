<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Trailer;

class TrailerPolicy
{
    public function view(User $user): bool
    {
        return $user->can('trailer.view');
    }

    public function create(User $user): bool
    {
        return $user->can('trailer.create');
    }

    public function show(User $user, Trailer $trailer): bool
    {
        return $user->can('trailer.view');
    }

    public function update(User $user, Trailer $trailer): bool
    {
        return $user->can('trailer.update');
    }

    public function delete(User $user, Trailer $trailer): bool
    {
        return $user->can('trailer.delete');
    }

    public function restore(User $user): bool
    {
        return $user->can('trailer.restore');
    }

    public function toggleStatus(User $user, Trailer $trailer): bool
    {
        return $user->can('trailer.toggle-status');
    }
}
