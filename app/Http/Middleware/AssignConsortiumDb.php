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
       // Enforce consortium selection here instead of in view to permit superadmin to login without
       // having to chooee (which would connect them to the con_template database)
        if ($request->isMethod('post') && $request->getPathInfo() == "/login") {
            if (is_null($request['consortium'])) {
                if ($request->email == config('ccplus.global_admin')) {
                    $request['consortium'] = "con_template";
                    session(['ccp_con_key' => "con_template"]);
                } else {
                    return back()->with('error',' A consortium selection is required');
                }
            }
        }

       // Check to ensure that the consortium key session variable is set.
        $paths = array("/login", "/forgot-password", "/reset-password");
       // Set consortium key variable for the session based on $request for a login request.
        if (session('ccp_con_key', '') == '') {
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
