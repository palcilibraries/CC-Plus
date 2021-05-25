<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use DB;
use Storage;

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
        $paths = array("/login", "/forgot-password", "/reset-password");
        if (session('ccp_con_key', '') == '') {
           // Set session based on $request for a login request.
            // if ($request->getPathInfo() == "/login" && isset($request['consortium'])) {
            if (in_array($request->getPathInfo(),$paths) && isset($request['consortium'])) {
                session(['ccp_con_key' => $request['consortium']]);
           // Otherwise, logout and reset things
            } else {
                Auth::logout();
                return $next($request);
            }
        }

       // Use the consortium key set the database.
        config(['database.connections.consodb.database' => 'ccplus_' . session('ccp_con_key')]);

       // Connect the database and move on the to next request
        try {
            DB::reconnect();
        } catch (\Exception $e) {
            Storage::append('reconnect_fails.log', date('Y-m-d H:is') . ' : Reconnect attempt failed! Path: ' .
                            $request->getPathInfo());
            Auth::logout();
            return $next($request);
        }
        return $next($request);

    }
}
