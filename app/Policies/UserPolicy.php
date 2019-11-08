<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $self
     * @param  \App\User  $user
     * @return mixed
     */
    public function update(User $self, User $user)
    {
//        return auth()->user()->hasRole("Admin") ||
//              (auth()->user()->hasRole("Manager") && auth()->user()->inst_id == $user->inst_id) ||
//               auth()->user()->id == $user->id;
  //    return auth()->id == $user->id ||
  //           (auth()->user()->hasRole("Manager") && auth()->user()->inst_id == $user->inst_id);
        return $self->id == $user->id ||
             ($self->hasRole("Manager") && $self->inst_id == $user->inst_id);
    }
}
