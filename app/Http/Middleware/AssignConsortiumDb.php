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
        // If this is a login request and the session variable is unset,
        // set the session variable before trying to switch the databse.
        //
        if (!session()->has('ccp_con_key') && $request->getPathInfo() === "/login") {
            if (isset($request['consortium']) || array_key_exists('consortium', $request)) {
                session(['ccp_con_key' => $request['consortium']]);
            }
        }

        config(['database.connections.consodb.database' => 'ccplus_' . session('ccp_con_key')]);
        DB::reconnect();

        return $next($request);
    }
}
