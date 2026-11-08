<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(User $authUser, User $user): bool
    {
        return $authUser->can('View:User');
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(User $authUser, User $user): bool
    {
        return $authUser->can('Update:User');
    }

    public function delete(User $authUser, User $user): bool
    {
        return $authUser->isNot($user) && $authUser->can('Delete:User');
    }

    public function deleteAny(User $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(User $authUser, User $user): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(User $authUser, User $user): bool
    {
        return $authUser->can('ForceDelete:User');
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(User $authUser, User $user): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}
