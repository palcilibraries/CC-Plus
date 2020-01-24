<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use DB;

class AssignConsortiumDb
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
       // Check to ensure that the consortium key session variable is set.
        if (session('ccp_con_key','') == '') {
           // Set session based on $request for a login request.
            if ($request->getPathInfo() == "/login" && isset($request['consortium'])) {
                session(['ccp_con_key' => $request['consortium']]);
           // Otherwise, logout and reset things
            } else {
                Auth::logout();
                return $next($request);
            }
        }

       // Use the consortium key set the database.
        config(['database.connections.consodb.database' => 'ccplus_' . session('ccp_con_key')]);
        DB::reconnect();
        return $next($request);
     }
}
