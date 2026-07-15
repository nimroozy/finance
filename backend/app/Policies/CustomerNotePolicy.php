<?php

namespace App\Policies;

use App\Models\CustomerNote;
use App\Models\User;

class CustomerNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notes.view');
    }

    public function view(User $user, CustomerNote $note): bool
    {
        return $user->can('notes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('notes.create') || $user->can('notes.manage');
    }

    public function update(User $user, CustomerNote $note): bool
    {
        if ($user->can('notes.manage')) {
            return true;
        }

        return $user->can('notes.create') && (int) $note->author_id === (int) $user->id;
    }
}
