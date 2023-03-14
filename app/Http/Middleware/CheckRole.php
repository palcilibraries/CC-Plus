<?php

namespace App\Http\Middleware;

use Closure;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  \App\Role $roles
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = \Auth::user();
        if ($user->hasRole("ServerAdmin")) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(401, 'This action is unauthorized.');
    }
}
