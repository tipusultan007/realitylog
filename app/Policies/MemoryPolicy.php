<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;

class MemoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Memory $memory): bool
    {
        return $user->id === $memory->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Memory $memory): bool
    {
        return $user->id === $memory->user_id;
    }

    public function delete(User $user, Memory $memory): bool
    {
        return $user->id === $memory->user_id;
    }

    public function restore(User $user, Memory $memory): bool
    {
        return $user->id === $memory->user_id;
    }

    public function forceDelete(User $user, Memory $memory): bool
    {
        return $user->id === $memory->user_id;
    }
}
