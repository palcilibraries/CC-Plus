<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Role' => 'App\Policies\RolePolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        //
        // Register the Gate hook(s) here... these will execute before
        // everything else in the system at auth-time
        Gate::before(function ($user) {
            // return true here will bails us out of the authorization process
            // (would essentially allow all)
            return $user->hasRole("Admin");
        });
    }
}
